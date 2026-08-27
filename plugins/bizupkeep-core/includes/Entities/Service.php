<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Entities;

use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row of the BizUpKeep ecosystem's Service catalog - a stable,
 * code-facing identity for a purchasable service (Registration, Annual
 * Return Fee, an Amendment combination, Bookkeeping Monthly
 * Subscription) carrying just enough metadata (VAT treatment,
 * recurring-or-not, price) for a downstream consumer to reason about a
 * purchase without re-deriving it ad hoc. `priceMinor` is the
 * canonical price for a `Fixed`-pricing service (in cents; `null`
 * means "not yet configured by staff", not "free") - `product_sku`/
 * `product_slug` are retained only as historical metadata from the
 * now-removed WooCommerce-backed pricing this replaced, and are no
 * longer read by anything. A `Quoted`-pricing service (Annual Return)
 * never has a catalog price at all - its amount always comes from the
 * workflow's own per-client quote.
 *
 * @package BizUpKeep\Core\Entities
 */
final readonly class Service
{
    public function __construct(
        public string $uuid,
        public string $serviceKey,
        public string $name,
        public ServicePricingMode $pricingMode,
        public ?int $priceMinor,
        public ?string $productSku,
        public ?string $productSlug,
        public ServiceVatTreatment $vatTreatment,
        public bool $isRecurring,
        public string $notes,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->uuid === '') {
            throw new InvalidArgumentException('Service uuid cannot be empty.');
        }

        if ($this->serviceKey === '') {
            throw new InvalidArgumentException('Service serviceKey cannot be empty.');
        }

        if ($this->name === '') {
            throw new InvalidArgumentException('Service name cannot be empty.');
        }

        if ($this->pricingMode === ServicePricingMode::Quoted && $this->priceMinor !== null) {
            throw new InvalidArgumentException(
                'A Quoted-pricing service cannot have a catalog priceMinor - '
                . 'its amount always comes from the per-client quote.'
            );
        }

        if ($this->priceMinor !== null && $this->priceMinor < 0) {
            throw new InvalidArgumentException('Service priceMinor cannot be negative.');
        }

        $hasSku = $this->productSku !== null && $this->productSku !== '';
        $hasSlug = $this->productSlug !== null && $this->productSlug !== '';

        if ($hasSku === $hasSlug) {
            throw new InvalidArgumentException(
                'Service must have exactly one of productSku or productSlug set.'
            );
        }
    }
}
