<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Billing;

use BizHub\Bookkeeping\Contracts\InternalCompanyProviderInterface;
use BizHub\Bookkeeping\Exceptions\ValidationException;

/**
 * Thin wrapper around the WP option InternalBooksPage writes once
 * staff complete its one-time setup - the single supported way for
 * another module (BizUpKeep Payments) to resolve A2Z's own company
 * UUID, instead of reading the option key directly.
 *
 * @package BizHub\Bookkeeping\Billing
 */
final class InternalCompanyProvider implements InternalCompanyProviderInterface
{
    public const OPTION_COMPANY_UUID = 'bizupkeep_bookkeeping_internal_company_uuid';

    public function getUuid(): ?string
    {
        $companyUuid = get_option(self::OPTION_COMPANY_UUID);

        return is_string($companyUuid) && $companyUuid !== '' ? $companyUuid : null;
    }

    public function requireUuid(): string
    {
        return $this->getUuid() ?? throw new ValidationException(
            'A2Z\'s Internal Books company has not been set up yet - visit Internal Books in wp-admin first.'
        );
    }

    public function setUuid(string $companyUuid): void
    {
        update_option(self::OPTION_COMPANY_UUID, $companyUuid);
    }

    public function clear(): void
    {
        delete_option(self::OPTION_COMPANY_UUID);
    }
}
