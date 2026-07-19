<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Contracts;

/**
 * Defines the authorization service contract.
 *
 * @package BizHub\Security\Authorization\Contracts
 */
interface AuthorizationServiceInterface
{
    /**
     * Determine whether a user has a capability.
     *
     * @param int                  $userId     WordPress user ID.
     * @param string               $capability Capability name.
     * @param array<string,mixed>  $context    Optional context.
     *
     * @return bool
     */
    public function can(
        int $userId,
        string $capability,
        array $context = []
    ): bool;

    /**
     * Register a capability.
     *
     * @param string $capability Capability name.
     *
     * @return void
     */
    public function registerCapability(
        string $capability
    ): void;

    /**
     * Return all registered capabilities.
     *
     * @return array<int,string>
     */
    public function capabilities(): array;
}
