<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Contracts;

use BizHub\Bookkeeping\Exceptions\ValidationException;

/**
 * Resolves the UUID of A2Z's own "Internal Books" company - the one
 * BizUpKeep uses to run its own books via InternalBooksPage, and the
 * one every BizUpKeep Payments invoice/revenue posting must be issued
 * from (never a client's own company - see InvoiceServiceInterface's
 * assertActiveSubscription() gate, which this specifically avoids by
 * always invoicing under a company whose own subscription is
 * permanently extended).
 *
 * @package BizHub\Bookkeeping\Contracts
 */
interface InternalCompanyProviderInterface
{
    /**
     * Return A2Z's Internal Books company UUID, or null if staff have
     * never completed the one-time setup on InternalBooksPage.
     */
    public function getUuid(): ?string;

    /**
     * Same as getUuid(), but throws instead of returning null - for a
     * call site (e.g. BizUpKeep Payments' webhook handler) that cannot
     * proceed at all without a real A2Z company to invoice from.
     *
     * @throws ValidationException
     */
    public function requireUuid(): string;

    /**
     * Record A2Z's company UUID - called once by InternalBooksPage's
     * setup form.
     */
    public function setUuid(string $companyUuid): void;

    /**
     * Clear a stored UUID that no longer resolves to a real company
     * (e.g. deleted by hand).
     */
    public function clear(): void;
}
