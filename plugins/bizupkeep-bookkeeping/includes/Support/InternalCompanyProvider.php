<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Support;

use BizHub\Bookkeeping\Contracts\InternalCompanyProviderInterface;
use RuntimeException;

/**
 * Reads the WP option Admin\InternalBooksPage writes once it has
 * lazily created A2Z's own Company/Client record - the single source
 * of truth for "which company is A2Z's own books". Also read directly
 * (by the same option name) from astra-child's
 * bizupkeep_child_post_a2z_revenue(), which is left as-is; this class
 * exists so plugins other than the theme (BizUpKeep Payments'
 * PaymentConfirmationService) can depend on an interface instead of a
 * raw get_option() call.
 *
 * @package BizHub\Bookkeeping\Support
 */
final class InternalCompanyProvider implements InternalCompanyProviderInterface
{
    private const OPTION_COMPANY_UUID = 'bizupkeep_bookkeeping_internal_company_uuid';

    public function getUuid(): ?string
    {
        $uuid = get_option(self::OPTION_COMPANY_UUID);

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function requireUuid(): string
    {
        $uuid = $this->getUuid();

        if ($uuid === null) {
            throw new RuntimeException(
                'Internal Books has not been set up yet - visit the Internal Books admin page first.'
            );
        }

        return $uuid;
    }
}
