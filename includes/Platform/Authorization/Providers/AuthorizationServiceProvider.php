<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Platform\Authorization\Services\AuthorizationService;
use BizHub\Platform\Authorization\Services\CapabilityRegistry;
use BizHub\Platform\Authorization\Services\PolicyResolver;

/**
 * Registers the Authorization platform services.
 *
 * @package BizHub\Platform\Authorization\Providers
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    private CapabilityRegistry $capabilityRegistry;

    private PolicyResolver $policyResolver;

    private AuthorizationService $authorizationService;

    public function __construct()
    {
        $this->capabilityRegistry = new CapabilityRegistry();

        $this->policyResolver = new PolicyResolver();

        $this->authorizationService = new AuthorizationService();
    }

    /**
     * Boot the Authorization platform.
     *
     * @return void
     */
    public function boot(): void
    {
        error_log('[BizHub] Authorization Provider Booted');
    }

    /**
     * Get the authorization service.
     */
    public function authorization(): AuthorizationService
    {
        return $this->authorizationService;
    }

    /**
     * Get the capability registry.
     */
    public function registry(): CapabilityRegistry
    {
        return $this->capabilityRegistry;
    }

    /**
     * Get the policy resolver.
     */
    public function resolver(): PolicyResolver
    {
        return $this->policyResolver;
    }
}