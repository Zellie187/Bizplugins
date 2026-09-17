<?php

declare(strict_types=1);

namespace BizHub\Payments\Services;

use BizHub\Payments\Exceptions\ValidationException;
use BizHub\Workflow\Contracts\WorkflowTypeServiceInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnService;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentService;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;

/**
 * Maps a workflow instance to the Service catalog key it is being
 * paid for, and to the concrete WorkflowTypeServiceInterface
 * implementation that advances it - the plugin-side counterpart of
 * astra-child's bizupkeep_child_resolve_amendment_service_key() /
 * bizupkeep_child_workflow_type_service() helpers. The amendment
 * sort-and-join logic below is lifted verbatim from the theme's own
 * function, since it's pure catalog-resolution logic that both the
 * theme (for its own product/service display) and this plugin (for
 * pricing/invoicing) need identically.
 *
 * Constructor-injects all three concrete workflow-type services
 * directly, matching QualityReviewPage's own established pattern for
 * this exact same "pick the right one of three by workflow type" need
 * - simpler than resolving them from the raw DI container at runtime.
 *
 * @package BizHub\Payments\Services
 */
final class ServiceKeyResolver
{
    public function __construct(
        private readonly CompanyRegistrationService $registrations,
        private readonly CompanyAmendmentService $amendments,
        private readonly AnnualReturnService $annualReturns
    ) {
    }

    /**
     * @throws ValidationException If the instance's workflow type is unrecognized, or (for
     *                              Company Amendment) its amendment_types metadata is empty/
     *                              invalid.
     */
    public function serviceKeyFor(WorkflowInstance $instance): string
    {
        return match ($instance->getWorkflowType()) {
            CompanyRegistrationDefinition::TYPE => 'registration',
            AnnualReturnDefinition::TYPE => 'annual_return_fee',
            CompanyAmendmentDefinition::TYPE => $this->amendmentServiceKey($instance),
            default => throw new ValidationException(sprintf(
                'Unrecognized workflow type "%s".',
                $instance->getWorkflowType()
            )),
        };
    }

    /**
     * @throws ValidationException If the workflow type is unrecognized.
     */
    public function workflowServiceFor(WorkflowInstance $instance): WorkflowTypeServiceInterface
    {
        return match ($instance->getWorkflowType()) {
            CompanyRegistrationDefinition::TYPE => $this->registrations,
            AnnualReturnDefinition::TYPE => $this->annualReturns,
            CompanyAmendmentDefinition::TYPE => $this->amendments,
            default => throw new ValidationException(sprintf(
                'Unrecognized workflow type "%s".',
                $instance->getWorkflowType()
            )),
        };
    }

    private function amendmentServiceKey(WorkflowInstance $instance): string
    {
        $requested = $instance->getMetadata()['amendment_types'] ?? [];
        $types = is_array($requested)
            ? array_values(array_intersect($requested, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES))
            : [];

        if ($types === []) {
            throw new ValidationException('This Company Amendment has no valid amendment_types set.');
        }

        sort($types);

        return 'amendment_' . implode('_', $types);
    }
}
