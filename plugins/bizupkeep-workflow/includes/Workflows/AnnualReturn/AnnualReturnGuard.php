<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\AnnualReturn;

use BizHub\Workflow\Contracts\TransitionGuardInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;

/**
 * Enforces the Annual Return workflow's business rules.
 *
 * @package BizHub\Workflow\Workflows\AnnualReturn
 */
final class AnnualReturnGuard implements TransitionGuardInterface
{
    /**
     * {@inheritDoc}
     */
    public function guard(
        WorkflowInstance $workflow,
        WorkflowStatus $to,
        string $action,
        array $context
    ): void {
        match ($action) {
            AnnualReturnDefinition::ACTION_REQUEST_PAYMENT,
            AnnualReturnDefinition::ACTION_REVISE_QUOTE => $this->guardQuoteAmount($context),
            AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT => $this->guardConfirmPayment($context),
            AnnualReturnDefinition::ACTION_APPROVE => $this->guardApprove($context),
            default => null,
        };
    }

    /**
     * Requiring a quote amount here is what turns "request payment"
     * into "staff have checked CIPC and are now quoting the client a
     * specific amount" - per the workflow spec's "staff to check
     * annual returns on CIPC site >> send quote to client" step, which
     * this guard is the only enforcement point for (nothing upstream
     * of it knows or cares what CIPC actually says). Shared with
     * revise_quote, which carries the exact same precondition - it's
     * just the same requirement applied to a workflow that's already
     * been quoted once.
     *
     * @param array<string,mixed> $context
     */
    private function guardQuoteAmount(array $context): void
    {
        $amount = $context['quote_amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount <= 0.0) {
            throw new PreconditionFailedException(
                'A quote amount greater than zero is required to send a payment request.'
            );
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function guardConfirmPayment(array $context): void
    {
        $reference = $context['payment_reference'] ?? '';

        if (! is_string($reference) || trim($reference) === '') {
            throw new PreconditionFailedException(
                'A payment reference is required to confirm payment has been received.'
            );
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function guardApprove(array $context): void
    {
        $reviewer = $context['reviewed_by'] ?? '';

        if (! is_string($reviewer) || trim($reviewer) === '') {
            throw new PreconditionFailedException(
                'Quality review approval must record who confirmed the CIPC acknowledgement.'
            );
        }
    }
}
