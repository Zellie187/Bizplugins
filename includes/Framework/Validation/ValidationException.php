<?php

declare(strict_types=1);

namespace BizHub\Framework\Validation;

use BizHub\Framework\Exceptions\FrameworkException;

/**
 * Thrown when data fails validation.
 *
 * @package BizHub\Framework\Validation
 */
final class ValidationException extends FrameworkException
{
    /**
     * @param array<string,array<int,string>> $errors Field-keyed validation errors.
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message);
    }

    /**
     * Create an exception from a failed Validator instance.
     */
    public static function fromValidator(Validator $validator): self
    {
        return new self($validator->errors());
    }

    /**
     * Return the field-keyed validation errors.
     *
     * @return array<string,array<int,string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
