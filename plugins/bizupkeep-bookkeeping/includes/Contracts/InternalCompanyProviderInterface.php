<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

/**
 * Resolves A2Z's own "Internal Books" company UUID - the company every
 * A2Z-side revenue/expense posting (see astra-child's
 * bizupkeep_child_post_a2z_revenue(), BizUpKeep Payments'
 * PaymentConfirmationService) posts against, set up once via
 * Admin\InternalBooksPage and remembered as a WP option.
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface InternalCompanyProviderInterface
{
    /**
     * The Internal Books company's UUID, or null if InternalBooksPage
     * has never been set up on this site yet.
     */
    public function getUuid(): ?string;

    /**
     * Same as getUuid(), but throws instead of returning null - for
     * callers (like PaymentConfirmationService) that cannot proceed at
     * all without it.
     *
     * @throws \RuntimeException If Internal Books has not been set up yet.
     */
    public function requireUuid(): string;
}
