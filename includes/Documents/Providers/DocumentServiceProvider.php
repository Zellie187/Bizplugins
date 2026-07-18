<?php

declare(strict_types=1);

namespace BizHub\Documents\Providers;

use BizHub\Documents\Services\DocumentSecurityService;
use BizHub\Documents\Services\DocumentService;
use BizHub\Documents\Services\DocumentStorageService;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * Documents Service Provider.
 *
 * Exposes the Documents module's services to the rest of the
 * application. Bindings are declared in Documents/definitions.php.
 *
 * @package BizHub\Documents\Providers
 */
final class DocumentServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentStorageService $storageService,
        private readonly DocumentSecurityService $securityService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function register(): void
    {
        // Bindings are declared in Documents/definitions.php.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
    }

    /**
     * Return the Document service.
     */
    public function documentService(): DocumentService
    {
        return $this->documentService;
    }

    /**
     * Return the Document storage service.
     */
    public function storageService(): DocumentStorageService
    {
        return $this->storageService;
    }

    /**
     * Return the Document security service.
     */
    public function securityService(): DocumentSecurityService
    {
        return $this->securityService;
    }
}
