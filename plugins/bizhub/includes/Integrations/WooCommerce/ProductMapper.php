<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

/**
 * Maps WooCommerce products to BizHub application types.
 *
 * The mapping is read from WooCommerce product meta
 * ("_bizhub_application_type"), so it can be configured per-product
 * from the WordPress admin without code changes.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class ProductMapper
{
    private const META_KEY = '_bizhub_application_type';

    /**
     * Determine whether a product is tracked by BizHub (i.e. purchasing
     * it should create an application).
     */
    public function isTracked(int $productId): bool
    {
        return $this->applicationTypeForProduct($productId) !== null;
    }

    /**
     * Return the BizHub application type a product maps to, if any.
     */
    public function applicationTypeForProduct(int $productId): ?string
    {
        $type = get_post_meta($productId, self::META_KEY, true);

        return \is_string($type) && $type !== '' ? $type : null;
    }

    /**
     * Assign a product to a BizHub application type.
     */
    public function mapProductToApplicationType(int $productId, string $applicationType): void
    {
        update_post_meta($productId, self::META_KEY, $applicationType);
    }

    /**
     * Remove a product's BizHub application type mapping.
     */
    public function unmapProduct(int $productId): void
    {
        delete_post_meta($productId, self::META_KEY);
    }
}
