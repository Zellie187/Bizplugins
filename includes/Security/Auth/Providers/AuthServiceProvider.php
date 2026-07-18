<?php

declare(strict_types=1);

namespace BizHub\Security\Auth\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Security\Auth\RememberMe;

/**
 * Auth Service Provider.
 *
 * @package BizHub\Security\Auth\Providers
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly RememberMe $rememberMe
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        $this->rememberMe->register();
    }
}
