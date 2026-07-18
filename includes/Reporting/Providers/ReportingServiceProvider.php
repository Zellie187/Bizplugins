<?php

declare(strict_types=1);

namespace BizHub\Reporting\Providers;

use BizHub\Framework\Providers\ServiceProvider;
use BizHub\Reporting\ApplicationReport;
use BizHub\Reporting\CompanyReport;
use BizHub\Reporting\RevenueReport;
use BizHub\Reporting\UserActivityReport;

/**
 * Reporting Service Provider.
 *
 * @package BizHub\Reporting\Providers
 */
final class ReportingServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly CompanyReport $companyReport,
        private readonly ApplicationReport $applicationReport,
        private readonly RevenueReport $revenueReport,
        private readonly UserActivityReport $userActivityReport
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
     * Return the company report.
     */
    public function companyReport(): CompanyReport
    {
        return $this->companyReport;
    }

    /**
     * Return the application report.
     */
    public function applicationReport(): ApplicationReport
    {
        return $this->applicationReport;
    }

    /**
     * Return the revenue report.
     */
    public function revenueReport(): RevenueReport
    {
        return $this->revenueReport;
    }

    /**
     * Return the user activity report.
     */
    public function userActivityReport(): UserActivityReport
    {
        return $this->userActivityReport;
    }
}
