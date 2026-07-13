<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Platform\Authorization\Services\AuthorizationService;
use BizHub\Platform\Authorization\Services\CapabilityRegistry;
use BizHub\Platform\Authorization\Services\PolicyResolver;

/**
 * Authorization Service Provider.
 *
 * Registers and boots the BizHub Authorization Platform.
 *
 * @package BizHub\Platform\Authorization\Providers
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Authorization Service.
     */
    private AuthorizationService $authorizationService;

    /**
     * Capability Registry.
     */
    private CapabilityRegistry $capabilityRegistry;

    /**
     * Policy Resolver.
     */
    private PolicyResolver $policyResolver;

    /**
     * AuthorizationServiceProvider constructor.
     *
     * All dependencies are resolved by the PHP-DI container.
     */
    public function __construct(
        AuthorizationService $authorizationService,
        CapabilityRegistry $capabilityRegistry,
        PolicyResolver $policyResolver
    ) {
        $this->authorizationService = $authorizationService;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->policyResolver = $policyResolver;
    }

    /**
     * Register Authorization services.
     *
     * This method is reserved for future service registration.
     */
    public function register(): void
    {
        // Future registrations.
    }

    /**
     * Boot the Authorization Platform.
     */
    public function boot(): void
    {
        /*
         * Temporary bootstrap verification.
         * Remove after Sprint 002 is complete.
         */
        error_log('[BizHub] Authorization Provider Booted');
    }

    /**
     * Return the Authorization Service.
     */
    public function authorization(): AuthorizationService
    {
        return $this->authorizationService;
    }

    /**
     * Return the Capability Registry.
     */
    public function capabilityRegistry(): CapabilityRegistry
    {
        return $this->capabilityRegistry;
    }

    /**
     * Return the Policy Resolver.
     */
    public function policyResolver(): PolicyResolver
    {
        return $this->policyResolver;
    }
}