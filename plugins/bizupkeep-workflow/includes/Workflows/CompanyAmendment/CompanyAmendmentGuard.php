<?php

declare(strict_types=1);

namespace BizHub\Workflow\Workflows\CompanyAmendment;

use BizHub\Workflow\Contracts\TransitionGuardInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Exceptions\PreconditionFailedException;

/**
 * Enforces the Company Amendment workflow's business rules.
 *
 * Unlike CompanyRegistrationGuard, several checks here depend on which
 * amendment type(s) the workflow covers - captured once, at creation,
 * in the workflow's metadata (`amendment_types`, plus the per-type
 * payload: `proposed_names`, `director_changes`, `new_address`) by
 * CompanyAmendmentService::start(). Guards read that metadata via
 * $workflow->getMetadata() rather than the current transition's
 * $context, since the amendment content itself doesn't change between
 * transitions - only the workflow's processing status does.
 *
 * @package BizHub\Workflow\Workflows\CompanyAmendment
 */
final class CompanyAmendmentGuard implements TransitionGuardInterface
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
            CompanyAmendmentDefinition::ACTION_REQUEST_DOCUMENTS => $this->guardRequestDocuments($workflow),
            CompanyAmendmentDefinition::ACTION_VERIFY_DOCUMENTS => $this->guardVerifyDocuments($workflow, $context),
            CompanyAmendmentDefinition::ACTION_CONFIRM_PAYMENT => $this->guardConfirmPayment($context),
            CompanyAmendmentDefinition::ACTION_APPROVE => $this->guardApprove($context),
            CompanyAmendmentDefinition::ACTION_REJECT_NAME => $this->guardRejectName($workflow),
            CompanyAmendmentDefinition::ACTION_RESUBMIT_NAMES => $this->guardResubmitNames($context),
            default => null,
        };
    }

    /**
     * At least one recognised amendment type must have been selected
     * when the workflow was created.
     */
    private function guardRequestDocuments(WorkflowInstance $workflow): void
    {
        $types = $this->amendmentTypes($workflow);

        if ($types === []) {
            throw new PreconditionFailedException(
                'At least one amendment type (director, name, or address) must be selected.'
            );
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function guardVerifyDocuments(WorkflowInstance $workflow, array $context): void
    {
        if (($context['documents_verified'] ?? false) !== true) {
            throw new PreconditionFailedException(
                'Documents cannot be verified until they have been reviewed and confirmed complete.'
            );
        }

        $types = $this->amendmentTypes($workflow);
        $metadata = $workflow->getMetadata();

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $types, true)) {
            $names = array_filter(
                (array) ($metadata['proposed_names'] ?? []),
                static fn (mixed $name): bool => is_string($name) && trim($name) !== ''
            );

            if ($names === []) {
                throw new PreconditionFailedException(
                    'At least one proposed company name is required for a name change.'
                );
            }
        }

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR, $types, true)) {
            $directorChanges = $metadata['director_changes'] ?? [];

            if (! is_array($directorChanges) || $directorChanges === []) {
                throw new PreconditionFailedException(
                    'At least one director change (add, remove, or update) is required for a director amendment.'
                );
            }
        }

        if (in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS, $types, true)) {
            $newAddress = $metadata['new_address'] ?? [];

            if (
                ! is_array($newAddress)
                || trim((string) ($newAddress['address_line_1'] ?? '')) === ''
                || trim((string) ($newAddress['city'] ?? '')) === ''
                || trim((string) ($newAddress['postal_code'] ?? '')) === ''
            ) {
                throw new PreconditionFailedException(
                    'A complete new registered address is required for an address change.'
                );
            }
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
     * Unlike Company Registration - where every workflow always
     * involves a proposed name - rejecting a name only makes sense
     * for an amendment that actually included a name change. The
     * admin UI already only offers this action for such instances
     * (see QualityReviewPage::canRejectName()), but the state machine
     * itself has no way to know an instance's amendment_types, so this
     * guard is what actually stops a director- or address-only
     * amendment from being sent to NamesRejected via a forged/direct
     * request.
     */
    private function guardRejectName(WorkflowInstance $workflow): void
    {
        if (! in_array(CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $this->amendmentTypes($workflow), true)) {
            throw new PreconditionFailedException(
                'This amendment does not include a name change, so its proposed name cannot be rejected.'
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

    /**
     * @return array<int,string>
     */
    private function amendmentTypes(WorkflowInstance $workflow): array
    {
        $types = $workflow->getMetadata()['amendment_types'] ?? [];

        if (! is_array($types)) {
            return [];
        }

        return array_values(array_intersect($types, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES));
    }
}
