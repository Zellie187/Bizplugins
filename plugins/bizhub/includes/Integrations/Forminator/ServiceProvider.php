<?php

declare(strict_types=1);

namespace BizHub\Integrations\Forminator;

use BizHub\Framework\Providers\ServiceProvider as BaseServiceProvider;

/**
 * Forminator Integration Service Provider.
 *
 * Only registers its hooks when Forminator is active.
 *
 * @package BizHub\Integrations\Forminator
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function __construct(
        private readonly FormSubmissionListener $submissionListener
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
        if (! class_exists('Forminator_API')) {
            return;
        }

        $this->submissionListener->register();
    }
}
