<?php

declare(strict_types=1);

namespace BizHub\Framework\Events;

use BizHub\Framework\Providers\ServiceProvider;

/**
 * Event Service Provider.
 *
 * Exposes the shared EventDispatcher instance to the rest of the
 * application. Bindings are declared in Framework/Container/definitions.php.
 *
 * @package BizHub\Framework\Events
 */
final class EventServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly EventDispatcher $dispatcher
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
    }

    /**
     * Return the shared event dispatcher.
     */
    public function dispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }
}
