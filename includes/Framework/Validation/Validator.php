<?php

declare(strict_types=1);

namespace BizHub\Framework\Validation;

use BizHub\Framework\Support\Arr;
use InvalidArgumentException;

/**
 * Validates an array of data against a set of pipe-delimited rules.
 *
 * Example:
 *
 *     $validator = new Validator(
 *         ['email' => 'user@example.com', 'age' => 17],
 *         ['email' => 'required|email', 'age' => 'required|numeric|min:18']
 *     );
 *
 *     if ($validator->fails()) {
 *         $errors = $validator->errors();
 *     }
 *
 * @package BizHub\Framework\Validation
 */
final class Validator
{
    /**
     * @var array<string,array<int,string>>
     */
    private array $errors = [];

    private bool $validated = false;

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules Field name => pipe-delimited rule string.
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules
    ) {
    }

    /**
     * Determine whether validation fails.
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Determine whether validation passes.
     */
    public function passes(): bool
    {
        if (! $this->validated) {
            $this->run();
        }

        return $this->errors === [];
    }

    /**
     * Return the validation errors, keyed by field.
     *
     * @return array<string,array<int,string>>
     */
    public function errors(): array
    {
        if (! $this->validated) {
            $this->run();
        }

        return $this->errors;
    }

    /**
     * Run validation and return the validated data.
     *
     * @return array<string,mixed>
     *
     * @throws ValidationException If validation fails.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw ValidationException::fromValidator($this);
        }

        return $this->data;
    }

    /**
     * Execute every configured rule against the data.
     */
    private function run(): void
    {
        $this->validated = true;
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = Arr::get($this->data, $field);

            foreach (explode('|', $ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }

                if (! Rules::required($value) && $rule !== 'required') {
                    continue;
                }

                if (! $this->applyRule($value, $rule)) {
                    $this->errors[$field][] = $this->message($field, $rule);
                }
            }
        }
    }

    /**
     * Apply a single "name" or "name:parameters" rule to a value.
     */
    private function applyRule(mixed $value, string $rule): bool
    {
        [$name, $parameters] = $this->parseRule($rule);

        return match ($name) {
            'required' => Rules::required($value),
            'email' => Rules::email($value),
            'numeric' => Rules::numeric($value),
            'integer' => Rules::integer($value),
            'boolean' => Rules::boolean($value),
            'min' => Rules::min($value, (float) ($parameters[0] ?? 0)),
            'max' => Rules::max($value, (float) ($parameters[0] ?? 0)),
            'in' => Rules::in($value, $parameters),
            default => throw new InvalidArgumentException(
                sprintf('Unknown validation rule "%s".', $name)
            ),
        };
    }

    /**
     * Parse a rule string into its name and parameters.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function parseRule(string $rule): array
    {
        if (! str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$name, $parameterString] = explode(':', $rule, 2);

        return [$name, explode(',', $parameterString)];
    }

    /**
     * Build a human-readable error message for a failed rule.
     */
    private function message(string $field, string $rule): string
    {
        [$name] = $this->parseRule($rule);

        return match ($name) {
            'required' => sprintf('%s is required.', $field),
            'email' => sprintf('%s must be a valid email address.', $field),
            'numeric' => sprintf('%s must be numeric.', $field),
            'integer' => sprintf('%s must be an integer.', $field),
            'boolean' => sprintf('%s must be a boolean.', $field),
            'min' => sprintf('%s is too short.', $field),
            'max' => sprintf('%s is too long.', $field),
            'in' => sprintf('%s is not a valid value.', $field),
            default => sprintf('%s is invalid.', $field),
        };
    }
}
