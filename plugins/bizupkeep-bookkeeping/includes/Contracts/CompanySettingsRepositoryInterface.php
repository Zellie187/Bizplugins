<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Entities\CompanySettings;

/**
 * Persistence contract for a company's plugin-owned bookkeeping
 * settings. A missing row means "not VAT registered" (the safe
 * default) - callers should treat findByCompanyUuid() === null the
 * same as an explicit isVatRegistered: false.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface CompanySettingsRepositoryInterface
{
    public function findByCompanyUuid(string $companyUuid): ?CompanySettings;

    public function save(CompanySettings $settings): CompanySettings;
}
