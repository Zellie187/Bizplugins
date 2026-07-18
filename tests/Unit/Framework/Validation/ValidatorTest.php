<?php

declare(strict_types=1);

namespace BizHub\Tests\Unit\Framework\Validation;

use BizHub\Framework\Validation\ValidationException;
use BizHub\Framework\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_passes_with_valid_data(): void
    {
        $validator = new Validator(
            ['email' => 'user@example.com', 'age' => 21],
            ['email' => 'required|email', 'age' => 'required|numeric|min:18']
        );

        $this->assertTrue($validator->passes());
        $this->assertFalse($validator->fails());
    }

    public function test_fails_with_invalid_data(): void
    {
        $validator = new Validator(
            ['email' => 'not-an-email', 'age' => 17],
            ['email' => 'required|email', 'age' => 'required|numeric|min:18']
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    public function test_validate_throws_on_failure(): void
    {
        $this->expectException(ValidationException::class);

        (new Validator(['email' => 'bad'], ['email' => 'required|email']))->validate();
    }

    public function test_validate_returns_data_on_success(): void
    {
        $data = ['email' => 'user@example.com'];

        $result = (new Validator($data, ['email' => 'required|email']))->validate();

        $this->assertSame($data, $result);
    }

    public function test_in_rule(): void
    {
        $validator = new Validator(['plan' => 'gold'], ['plan' => 'required|in:basic,pro']);

        $this->assertTrue($validator->fails());
    }
}
