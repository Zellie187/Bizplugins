<?php

declare(strict_types=1);

namespace BizHub\Framework\Contracts;

/**
 * Service provider contract.
 *
 * Service providers register framework services into the application
 * container and perform framework initialization.
 *
 * @package BizHub\Framework\Contracts
 */
interface ServiceProviderInterface
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Bootstrap registered services.
     *
     * @return void
     */
    public function boot(): void;
}