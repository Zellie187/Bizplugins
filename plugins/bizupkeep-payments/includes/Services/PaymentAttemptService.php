<?php

declare(strict_types=1);

namespace BizHub\Payments\Services;

use BizHub\Bookkeeping\Support\Money;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Entities\Client;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Framework\Support\Uuid;
use BizHub\Payments\Contracts\PaymentAttemptRepositoryInterface;
use BizHub\Payments\Contracts\PaymentAttemptServiceInterface;
use BizHub\Payments\Contracts\PaymentGatewayRegistryInterface;
use BizHub\Payments\DTO\CheckoutRequest;
use BizHub\Payments\DTO\PaymentAttemptStart;
use BizHub\Payments\Entities\PaymentAttempt;
use BizHub\Payments\Enums\GatewayName;
use BizHub\Payments\Enums\PaymentAttemptStatus;
use BizHub\Payments\Exceptions\ValidationException;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use DateTimeImmutable;

/**
 * @package BizHub\Payments\Services
 */
final class PaymentAttemptService implements PaymentAttemptServiceInterface
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly CompanyServiceInterface $companies,
        private readonly ClientServiceInterface $clients,
        private readonly ServiceRepositoryInterface $catalog,
        private readonly ServiceKeyResolver $serviceKeyResolver,
        private readonly PaymentAttemptRepositoryInterface $attempts,
        private readonly PaymentGatewayRegistryInterface $gateways
    ) {
    }

    public function startForWorkflow(string $workflowUuid, int $wpUserId, GatewayName $gateway): PaymentAttemptStart
    {
        $instance = $this->workflows->find($workflowUuid);

        if ($instance === null || $instance->getSubjectType() !== 'company') {
            throw new ValidationException('That application could not be found.');
        }

        if ($instance->getStatus() !== WorkflowStatus::AwaitingPayment) {
            throw new ValidationException('This application is not currently awaiting payment.');
        }

        $company = $this->ownedCompany($wpUserId, $instance->getSubjectUuid());
        $serviceKey = $this->serviceKeyResolver->serviceKeyFor($instance);
        $service = $this->requireService($serviceKey);
        $amountMinor = $this->resolveAmountMinor($service, $instance);

        return $this->startCheckout($gateway, $service, $company, $wpUserId, $amountMinor, $instance->getUuid());
    }

    public function startForBookkeepingSubscription(
        string $companyUuid,
        int $wpUserId,
        GatewayName $gateway
    ): PaymentAttemptStart {
        $company = $this->ownedCompany($wpUserId, $companyUuid);
        $service = $this->requireService('bookkeeping_monthly');
        $amountMinor = $this->resolveAmountMinor($service, null);

        return $this->startCheckout($gateway, $service, $company, $wpUserId, $amountMinor, null);
    }

    private function startCheckout(
        GatewayName $gateway,
        Service $service,
        Company $company,
        int $wpUserId,
        int $amountMinor,
        ?string $workflowUuid
    ): PaymentAttemptStart {
        $attempt = new PaymentAttempt(
            uuid: Uuid::generate(),
            gateway: $gateway,
            gatewayReference: null,
            serviceKey: $service->serviceKey,
            companyUuid: $company->getUuid(),
            workflowUuid: $workflowUuid,
            buyerWpUserId: $wpUserId,
            amountMinor: $amountMinor,
            currency: 'ZAR',
            status: PaymentAttemptStatus::Created,
            invoiceUuid: null,
            customerUuid: null,
            failureReason: null,
            fulfilledAt: null,
            createdAt: new DateTimeImmutable(),
        );

        $this->attempts->save($attempt);

        $result = $this->gateways->get($gateway)->createCheckout(new CheckoutRequest(
            amountMinor: $amountMinor,
            currency: 'ZAR',
            reference: $attempt->uuid,
            description: $service->name,
            successUrl: $this->returnUrl('success', $attempt->uuid),
            cancelUrl: $this->returnUrl('cancelled', $attempt->uuid),
            failureUrl: $this->returnUrl('failed', $attempt->uuid),
        ));

        $redirected = $attempt->withRedirected($result->gatewayReference);
        $this->attempts->save($redirected);

        return new PaymentAttemptStart($redirected, $result->redirectUrl);
    }

    private function resolveAmountMinor(Service $service, ?WorkflowInstance $instance): int
    {
        if ($service->pricingMode === ServicePricingMode::Fixed) {
            if ($service->priceMinor === null) {
                throw new ValidationException(sprintf(
                    '"%s" does not have a price configured yet - contact staff.',
                    $service->name
                ));
            }

            return $service->priceMinor;
        }

        $quoteAmount = $instance?->getMetadata()['quote_amount'] ?? null;

        if (! is_numeric($quoteAmount) || (float) $quoteAmount <= 0) {
            throw new ValidationException('This application does not have a quote yet.');
        }

        return Money::fromRands((float) $quoteAmount)->minorUnits();
    }

    private function requireService(string $serviceKey): Service
    {
        $service = $this->catalog->findByKey($serviceKey);

        if ($service === null || ! $service->isActive) {
            throw new ValidationException(sprintf('Service "%s" is not currently available.', $serviceKey));
        }

        return $service;
    }

    private function ownedCompany(int $wpUserId, string $companyUuid): Company
    {
        try {
            $client = $this->clients->getClientByWpUserId($wpUserId);
        } catch (ClientNotFoundException) {
            throw new ValidationException('No client account found for this user.');
        }

        try {
            $company = $this->companies->getCompany($companyUuid);
        } catch (CompanyNotFoundException) {
            throw new ValidationException('That company could not be found.');
        }

        if ($company->getClientId() !== $client->getId()) {
            throw new ValidationException('This company does not belong to you.');
        }

        return $company;
    }

    private function returnUrl(string $outcome, string $attemptUuid): string
    {
        return add_query_arg(
            ['bizupkeep_payment_attempt' => $attemptUuid, 'bizupkeep_payment_outcome' => $outcome],
            home_url('/')
        );
    }
}
