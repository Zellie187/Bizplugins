<?php

declare(strict_types=1);

namespace BizHub\Security\Encryption;

/**
 * One-way hashing for passwords and other secrets.
 *
 * @package BizHub\Security\Encryption
 */
final class Hasher
{
    /**
     * Hash a plaintext value.
     */
    public function make(string $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * Determine whether a plaintext value matches a previously hashed value.
     */
    public function check(string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($value, $hash);
    }

    /**
     * Determine whether a hash was created with outdated options and
     * should be regenerated.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
