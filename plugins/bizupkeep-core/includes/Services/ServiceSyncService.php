<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Services;

use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use WC_Product;
use WC_Product_Simple;
use WP_Post;

/**
 * The only class in this plugin that reads or writes a WooCommerce
 * product. WooCommerce itself stays the single source of truth for
 * "what is the current price" at all times - this service resolves a
 * Service catalog row to its linked WC product, reads its live price
 * for display, and pushes a staff-submitted price straight back onto
 * that product on save. It never caches a price anywhere.
 *
 * Deliberately does not touch, replace, or depend on
 * astra-child/functions.php's own lazy-creation functions for the
 * Annual Return Fee / Bookkeeping Monthly placeholder products
 * (bizupkeep_child_get_or_create_annual_return_fee_product() /
 * bizupkeep_child_get_or_create_bookkeeping_subscription_product()) -
 * both sides do their own independent lookup-or-create against the
 * same SKU, which WooCommerce's own unique-SKU constraint makes safe
 * to run from either side without ever creating a duplicate.
 *
 * @package BizUpKeep\Core\Services
 */
final class ServiceSyncService
{
    /**
     * Resolve a Service catalog row to its linked WooCommerce product
     * ID, or null if WooCommerce is inactive or no matching product
     * exists yet.
     */
    public function resolveProductId(Service $service): ?int
    {
        if (! class_exists(WC_Product_Simple::class)) {
            return null;
        }

        if ($service->productSku !== null) {
            $id = wc_get_product_id_by_sku($service->productSku);

            return $id > 0 ? (int) $id : null;
        }

        if ($service->productSlug !== null && function_exists('get_page_by_path')) {
            $product = get_page_by_path($service->productSlug, OBJECT, 'product');

            return $product instanceof WP_Post ? (int) $product->ID : null;
        }

        return null;
    }

    /**
     * The linked product's current live price, in integer cents.
     * Null for a Quoted-pricing-mode service (no fixed price to
     * show), for a service with no resolvable product, or when
     * WooCommerce is inactive.
     */
    public function currentPriceMinor(Service $service): ?int
    {
        if ($service->pricingMode === ServicePricingMode::Quoted) {
            return null;
        }

        $product = $this->resolveProduct($service);

        if ($product === null) {
            return null;
        }

        $price = $product->get_regular_price();

        return $price === '' ? 0 : (int) round(round((float) $price, 2) * 100);
    }

    /**
     * Push a staff-submitted price (integer cents) onto the linked
     * product. Registration/Amendment (product_slug-identified) are
     * update-only: no product is ever created for them here - a
     * missing product is a real data problem to surface, not to
     * silently paper over with a mismatched new product. Bookkeeping
     * Monthly is the one row allowed to lazily create its product if
     * missing, mirroring the theme's own equivalent for that product.
     * Never called for the Quoted-pricing Annual Return Fee row.
     */
    public function updatePrice(Service $service, int $priceMinor): bool
    {
        if ($service->pricingMode === ServicePricingMode::Quoted) {
            return false;
        }

        if (! class_exists(WC_Product_Simple::class) || ! function_exists('wc_get_product')) {
            return false;
        }

        $productId = $this->resolveProductId($service);

        if ($productId === null) {
            if ($service->serviceKey !== 'bookkeeping_monthly') {
                return false;
            }

            $productId = $this->createPlaceholderProduct($service);

            if ($productId === null) {
                return false;
            }
        }

        $product = wc_get_product($productId);

        if (! $product) {
            return false;
        }

        $rands = number_format($priceMinor / 100, 2, '.', '');
        $product->set_regular_price($rands);
        $product->set_price($rands);
        $product->save();

        return true;
    }

    /**
     * Ensure the Annual Return Fee's placeholder product exists,
     * mirroring the theme's own
     * bizupkeep_child_get_or_create_annual_return_fee_product() - never
     * writes a price, since that product's real price is always a
     * per-client quote applied dynamically at cart time.
     */
    public function ensureExists(Service $service): ?int
    {
        $existing = $this->resolveProductId($service);

        return $existing ?? $this->createPlaceholderProduct($service);
    }

    private function resolveProduct(Service $service): ?WC_Product
    {
        $productId = $this->resolveProductId($service);

        if ($productId === null || ! function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);

        return $product instanceof WC_Product ? $product : null;
    }

    private function createPlaceholderProduct(Service $service): ?int
    {
        if (! class_exists(WC_Product_Simple::class) || $service->productSku === null) {
            return null;
        }

        $product = new WC_Product_Simple();
        $product->set_name($service->name);
        $product->set_sku($service->productSku);
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_virtual(true);
        $product->set_catalog_visibility('hidden');
        $product->set_status('publish');

        $id = $product->save();

        return $id > 0 ? (int) $id : null;
    }
}
