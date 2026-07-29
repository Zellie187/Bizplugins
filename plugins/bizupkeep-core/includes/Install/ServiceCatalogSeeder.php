<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Install;

use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use DateTimeImmutable;

/**
 * Seeds the Service catalog's fixed row set at activation time. Never
 * touches a row whose service_key already exists, so a staff edit
 * (e.g. changing vat_treatment once a real decision is made) survives
 * every future reactivation/upgrade untouched.
 *
 * The row set mirrors astra-child/functions.php's real WooCommerce
 * product identifiers (BIZUPKEEP_REGISTRATION_PRODUCT_SLUG,
 * BIZUPKEEP_ANNUAL_RETURN_FEE_PRODUCT_SKU,
 * BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS,
 * BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_PRODUCT_SKU) as of the current
 * checkout flows - this file is the source of truth for the catalog,
 * the theme constants remain the source of truth for the real
 * WooCommerce products themselves.
 *
 * vat_treatment defaults to None for every service except Bookkeeping
 * Monthly: no VAT handling exists anywhere in the current checkout
 * code for Registration/Annual Return/Amendment, so this honestly
 * reflects that rather than guessing a treatment nothing enforces yet.
 * Staff can change it once a real decision is made, via the Service
 * Catalog admin page.
 *
 * @package BizUpKeep\Core\Install
 */
final class ServiceCatalogSeeder
{
    public function __construct(
        private readonly ServiceRepositoryInterface $repository
    ) {
    }

    public function seed(): void
    {
        foreach ($this->rows() as $row) {
            if ($this->repository->findByKey($row['service_key']) !== null) {
                continue;
            }

            $this->repository->save(new Service(
                uuid: wp_generate_uuid4(),
                serviceKey: $row['service_key'],
                name: $row['name'],
                pricingMode: $row['pricing_mode'],
                productSku: $row['product_sku'],
                productSlug: $row['product_slug'],
                vatTreatment: $row['vat_treatment'],
                isRecurring: $row['is_recurring'],
                notes: $row['notes'],
                isActive: true,
                createdAt: new DateTimeImmutable(),
            ));
        }
    }

    /**
     * @return array<int,array{
     *     service_key: string,
     *     name: string,
     *     pricing_mode: ServicePricingMode,
     *     product_sku: ?string,
     *     product_slug: ?string,
     *     vat_treatment: ServiceVatTreatment,
     *     is_recurring: bool,
     *     notes: string
     * }>
     */
    private function rows(): array
    {
        $noVatHandlingNote = 'No VAT handling exists in the checkout code today.';

        return [
            [
                'service_key' => 'registration',
                'name' => 'Company Registration',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'new-company-registration',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'annual_return_fee',
                'name' => 'Annual Return Fee',
                'pricing_mode' => ServicePricingMode::Quoted,
                'product_sku' => 'bizupkeep-annual-return-fee',
                'product_slug' => null,
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => 'Price is per-client quote, not catalog-fixed. ' . $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_director',
                'name' => 'Amendment: Director Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'director-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_name',
                'name' => 'Amendment: Name Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'name-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_address',
                'name' => 'Amendment: Address Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'address-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_director_name',
                'name' => 'Amendment: Director + Name Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'director-name-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_address_director',
                'name' => 'Amendment: Address + Director Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'director-address-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_address_name',
                'name' => 'Amendment: Address + Name Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'name-address-change',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'amendment_address_director_name',
                'name' => 'Amendment: Address + Director + Name Change',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => null,
                'product_slug' => 'all-in-one-director-name-address',
                'vat_treatment' => ServiceVatTreatment::None,
                'is_recurring' => false,
                'notes' => $noVatHandlingNote,
            ],
            [
                'service_key' => 'bookkeeping_monthly',
                'name' => 'Bookkeeping Monthly Subscription',
                'pricing_mode' => ServicePricingMode::Fixed,
                'product_sku' => 'bizupkeep-bookkeeping-monthly',
                'product_slug' => null,
                'vat_treatment' => ServiceVatTreatment::Inclusive,
                'is_recurring' => true,
                'notes' => 'Placeholder R0 price in wp-admin must be set by staff.',
            ],
        ];
    }
}
