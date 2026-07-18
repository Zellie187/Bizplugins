<?php

declare(strict_types=1);

namespace BizHub\Companies\Providers;

use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Services\CompanyLookupService;
use BizHub\Companies\Services\DirectorService;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Companies Service Provider.
 *
 * Exposes the Companies module's services to the rest of the
 * application. Bindings are declared in Companies/definitions.php.
 *
 * @package BizHub\Companies\Providers
 */
final class CompanyServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly CompanyServiceInterface $companyService,
        private readonly CompanyLookupService $lookupService,
        private readonly DirectorService $directorService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in Companies/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the Company service.
     */
    public function companyService(): CompanyServiceInterface
    {
        return $this->companyService;
    }

    /**
     * Return the Company lookup service.
     */
    public function lookupService(): CompanyLookupService
    {
        return $this->lookupService;
    }

    /**
     * Return the Director service.
     */
    public function directorService(): DirectorService
    {
        return $this->directorService;
    }
}
