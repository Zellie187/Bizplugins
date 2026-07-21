<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyRegistration;

use BizHub\Workflow\Contracts\TransitionGuardInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;

/**
 * Enforces the Company Registration workflow's business rules -
 * preconditions the state machine alone cannot express, since it only
 * knows which statuses an action may run from, not what evidence must
 * exist first.
 *
 * @package BizHub\Workflow\Workflows\CompanyRegistration
 */
final class CompanyRegistrationGuard implements TransitionGuardInterface
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
            CompanyRegistrationDefinition::ACTION_VERIFY_DOCUMENTS => $this->guardVerifyDocuments($context),
            CompanyRegistrationDefinition::ACTION_CONFIRM_PAYMENT => $this->guardConfirmPayment($context),
            CompanyRegistrationDefinition::ACTION_APPROVE => $this->guardApprove($context),
            CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES => $this->guardResubmitNames($context),
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $context
     */
    private function guardVerifyDocuments(array $context): void
    {
        if (($context['documents_verified'] ?? false) !== true) {
            throw new PreconditionFailedException(
                'Documents cannot be verified until they have been reviewed and confirmed complete.'
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
                'Quality review approval must record who performed the review.'
            );
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function guardResubmitNames(array $context): void
    {
        $names = $context['proposed_names'] ?? [];

        if (! is_array($names)) {
            throw new PreconditionFailedException(
                'At least one proposed company name is required to resubmit.'
            );
        }

        $hasNonBlankName = false;

        foreach ($names as $name) {
            if (is_string($name) && trim($name) !== '') {
                $hasNonBlankName = true;

                break;
            }
        }

        if (! $hasNonBlankName) {
            throw new PreconditionFailedException(
                'At least one proposed company name is required to resubmit.'
            );
        }
    }
}
