<?php

declare(strict_types=1);

namespace BizHub\Api;

use BizHub\Api\V1\ApplicationController;
use BizHub\Api\V1\CompanyController;
use BizHub\Api\V1\DocumentController;
use BizHub\Api\V1\ProfileController;
use BizHub\Framework\Providers\ServiceProvider;

/**
 * REST API Service Provider.
 *
 * Registers every V1 controller's routes on 'rest_api_init'.
 *
 * @package BizHub\Api
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly CompanyController $companyController,
        private readonly ApplicationController $applicationController,
        private readonly DocumentController $documentController,
        private readonly ProfileController $profileController
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
        add_action('rest_api_init', function (): void {
            $this->companyController->registerRoutes();
            $this->applicationController->registerRoutes();
            $this->documentController->registerRoutes();
            $this->profileController->registerRoutes();
        });
    }
}
