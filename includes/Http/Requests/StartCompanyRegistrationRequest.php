<?php

declare(strict_types=1);

namespace BizHub\Workflow\Http\Requests;

use BizHub\Framework\Support\Uuid;
use BizHub\Workflow\Exceptions\ValidationException;
use WP_REST_Request;

/**
 * Builds and validates the input required to start a Company
 * Registration workflow from a raw REST request.
 *
 * Per BH-WORKFLOW-SPEC-001 section 9, DTO/Request classes are
 * responsible for data-shape validation only; business-rule
 * validation (does the company exist?) happens in the Service layer.
 *
 * @package BizHub\Workflow\Http\Requests
 */
final readonly class StartCompanyRegistrationRequest
{
    private function __construct(
        public string $companyUuid,
    ) {
    }

    /**
     * @throws ValidationException If required fields are missing or malformed.
     */
    public static function fromRestRequest(WP_REST_Request $request): self
    {
        $companyUuid = trim((string) $request->get_param('company_uuid'));

        if ($companyUuid === '' || ! Uuid::isValid($companyUuid)) {
            throw new ValidationException(
                'A valid company_uuid is required.',
                ['company_uuid' => 'Must be a valid UUID identifying an existing company.']
            );
        }

        return new self($companyUuid);
    }
}
