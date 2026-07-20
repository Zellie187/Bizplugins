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
            AnnualReturnDefinition::ACTION_CONFIRM_PAYMENT => $this->guardConfirmPayment($context),
            AnnualReturnDefinition::ACTION_APPROVE => $this->guardApprove($context),
            default => null,
        };
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
