<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

use BizHub\Framework\Providers\ServiceProvider as BaseServiceProvider;

/**
 * WooCommerce Integration Service Provider.
 *
 * Only registers its hooks when WooCommerce is active.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function __construct(
        private readonly CheckoutHandler $checkoutHandler
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
        if (! class_exists('WooCommerce')) {
            return;
        }

        $this->checkoutHandler->register();
    }
}
