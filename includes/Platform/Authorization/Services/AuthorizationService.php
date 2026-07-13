<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Services;

use BizHub\Platform\Authorization\Contracts\AuthorizationServiceInterface;

/**
 * Authorization Service.
 *
 * Centralizes all permission checks within BizHub.
 *
 * WordPress capability functions should never be called directly
 * from business modules. They should always go through this service.
 *
 * @package BizHub\Platform\Authorization\Services
 */
final class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Registered BizHub capabilities.
     *
     * @var array<string,bool>
     */
    private array $capabilities = [];

    /**
     * {@inheritDoc}
     */
    public function can(
        int $userId,
        string $capability,
        array $context = []
    ): bool {

        if (! isset($this->capabilities[$capability])) {
            return false;
        }

        return user_can($userId, $capability);
    }

    /**
     * {@inheritDoc}
     */
    public function registerCapability(
        string $capability
    ): void {

        $this->capabilities[$capability] = true;
    }

    /**
     * {@inheritDoc}
     */
    public function capabilities(): array
    {
        return array_keys($this->capabilities);
    }
}