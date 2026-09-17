<?php

declare(strict_types=1);

namespace BizHub\Payments\Services;

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\InternalCompanyProviderInterface;
use BizHub\Bookkeeping\Contracts\InvoiceServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\DTO\InvoiceLineInput;
use BizHub\Bookkeeping\Enums\PaymentMethod;
use BizHub\Bookkeeping\Support\Money;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Framework\Logging\Logger;
use BizHub\Payments\Contracts\CustomerProvisionerInterface;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentConfirmationServiceInterface;
use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Exceptions\PaymentsException;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;
use Throwable;

/**
 * The critical-path class this whole plugin exists to run: turns a
 * gateway-confirmed payment into A2Z's own Invoice (its own revenue
 * record - see class docblock on InvoiceServiceInterface for why this
 * fully replaces the old TransactionCaptureService-based "A2Z revenue"
 * side-posting) and either advances the paying workflow or extends a
 * Bookkeeping Monthly subscription.
 *
 * Every InvoiceServiceInterface call here is made against A2Z's own
 * Internal Books company UUID, never $attempt->companyUuid (the
 * client's own company) - this is a load-bearing invariant, not a
 * style choice: InvoiceServiceInterface::sendInvoice()/recordPayment()
 * both gate on the invoicing company's own subscription being active,
 * and A2Z's Internal Books subscription is permanently extended
 * (InternalBooksPage::SUBSCRIPTION_EXTEND_DAYS), which is exactly what
 * lets a client with a *lapsed* Bookkeeping Monthly subscription still
 * pay to renew it - invoicing under the client's own (lapsed) company
 * would be a chicken-and-egg deadlock.
 *
 * @package BizHub\Payments\Services
 */
final class PaymentConfirmationService implements PaymentConfirmationServiceInterface
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
        private readonly CompanyServiceInterface $companies,
        private readonly InternalCompanyProviderInterface $internalCompany,
        private readonly CustomerProvisionerInterface $customerProvisioner,
        private readonly ServiceRepositoryInterface $catalog,
        private readonly AccountServiceInterface $accounts,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly InvoiceServiceInterface $invoicing,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly ServiceKeyResolver $serviceKeyResolver,
        private readonly PaymentAttemptRepositoryInterface $attempts,
        private readonly Logger $logger
    ) {
    }

    public function confirm(PaymentAttempt $attempt): void
    {
        try {
            $this->run($attempt);
        } catch (Throwable $exception) {
            // Money has already been captured (the gateway confirmed
            // it, and markSucceededOnce() already committed that
            // atomically before this method was ever called) - a
            // downstream failure here must never be reported back to
            // the gateway as retryable, and must never be silently
            // lost either. Logged loudly, left unfulfilled for staff
            // to retry manually via PaymentsDashboardPage.
            $this->logger->error('BizUpKeep Payments: payment confirmation failed after capture.', [
                'attempt_uuid' => $attempt->uuid,
                'gateway' => $attempt->gateway->value,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function run(PaymentAttempt $attempt): void
    {
        $internalCompanyUuid = $this->internalCompany->requireUuid();
        $client = $this->clients->getClientByWpUserId($attempt->buyerWpUserId);
        $company = $this->companies->getCompany($attempt->companyUuid);

        $customerUuid = $this->customerProvisioner->findOrCreate($internalCompanyUuid, $client, $company);
        $attempt = $this->attempts->save($attempt->withCustomer($customerUuid));

        $service = $this->catalog->findByKey($attempt->serviceKey);

        if ($service === null) {
            throw new PaymentsException(sprintf('Service "%s" no longer exists in the catalog.', $attempt->serviceKey));
        }

        $revenueAccount = $this->accounts->getByCode($internalCompanyUuid, ChartOfAccountsTemplate::CODE_SALES_REVENUE);
        $settings = $this->companySettings->findByCompanyUuid($internalCompanyUuid);
        $includesVat = $service->vatTreatment === ServiceVatTreatment::Inclusive
            && $settings !== null
            && $settings->isVatRegistered;

        $now = new DateTimeImmutable();

        $invoice = $this->invoicing->createInvoice(
            $internalCompanyUuid,
            $customerUuid,
            $revenueAccount->uuid,
            $includesVat,
            $now,
            $now,
            sprintf('%s - %s', $service->name, $company->getCompanyName()),
            [new InvoiceLineInput($service->name, 1, Money::fromMinorUnits($attempt->amountMinor))]
        );

        $sent = $this->invoicing->sendInvoice($internalCompanyUuid, $invoice->uuid, $attempt->buyerWpUserId);
        $this->invoicing->recordPayment(
            $internalCompanyUuid,
            $sent->uuid,
            PaymentMethod::Online,
            $attempt->buyerWpUserId
        );

        $attempt = $this->attempts->save($attempt->withInvoice($sent->uuid));

        if ($attempt->workflowUuid !== null) {
            $this->confirmWorkflow($attempt);
        } else {
            $this->subscriptions->extend($attempt->companyUuid, 30);
        }

        $this->attempts->save($attempt->withFulfilled(new DateTimeImmutable()));
    }

    private function confirmWorkflow(PaymentAttempt $attempt): void
    {
        $instance = $this->workflows->find((string) $attempt->workflowUuid);

        if ($instance === null || $instance->getStatus() !== WorkflowStatus::AwaitingPayment) {
            // Already confirmed by another means (e.g. a staff manual
            // override), or the workflow moved on for some other
            // reason since checkout was created - the payment itself
            // is still correctly captured and invoiced above, so this
            // is not treated as a failure of this method.
            return;
        }

        $this->serviceKeyResolver->workflowServiceFor($instance)->performAction(
            $instance->getUuid(),
            'confirm_payment',
            $attempt->buyerWpUserId,
            sprintf('Payment confirmed via %s (ref %s).', $attempt->gateway->value, $attempt->gatewayReference ?? ''),
            ['payment_reference' => $attempt->gatewayReference ?? '', 'invoice_uuid' => $attempt->invoiceUuid ?? '']
        );
    }
}
