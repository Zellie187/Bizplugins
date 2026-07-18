<?php

declare(strict_types=1);

namespace BizHub\Integrations\Forminator;

use BizHub\Framework\Validation\ValidationException;
use BizHub\Framework\Validation\Validator;

/**
 * Validates Forminator submission data before it is used to create a
 * BizHub application.
 *
 * @package BizHub\Integrations\Forminator
 */
final class Validation
{
    /**
     * Validate a mapped field-value array extracted from a Forminator entry.
     *
     * @param array<string,mixed> $fields
     *
     * @return array<string,mixed> The validated fields.
     *
     * @throws ValidationException If validation fails.
     */
    public function validate(array $fields): array
    {
        $validator = new Validator($fields, [
            'client_id' => 'required|integer',
            'email' => 'required|email',
        ]);

        return $validator->validate();
    }
}
