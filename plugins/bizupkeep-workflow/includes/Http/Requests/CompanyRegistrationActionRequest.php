<?php

declare(strict_types=1);

namespace BizHub\Workflow\Http\Requests;

use BizHub\Workflow\Exceptions\ValidationException;
use WP_REST_Request;

/**
 * Builds and validates the input required to perform an action
 * against an existing Company Registration workflow from a raw REST
 * request.
 *
 * @package BizHub\Workflow\Http\Requests
 */
final readonly class CompanyRegistrationActionRequest
{
    /**
     * @param array<string,mixed> $context
     */
    private function __construct(
        public string $action,
        public string $reason,
        public array $context,
    ) {
    }

    /**
     * @throws ValidationException If required fields are missing or malformed.
     */
    public static function fromRestRequest(WP_REST_Request $request): self
    {
        $action = trim((string) $request->get_param('action'));

        if ($action === '') {
            throw new ValidationException(
                'An action is required.',
                ['action' => 'This field is required.']
            );
        }

        $reason = trim((string) ($request->get_param('reason') ?? ''));

        $context = $request->get_param('context');
        $context = is_array($context) ? $context : [];

        return new self($action, $reason, $context);
    }
}
