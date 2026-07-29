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
 * recurring-or-not) for a downstream consumer to reason about a
 * purchase without re-deriving it ad hoc. Deliberately carries no
 * price: Registration/Amendment prices live on their real,
 * staff-managed WooCommerce products, Annual Return is per-client
 * quoted, and Bookkeeping Monthly's fixed price already lives on its
 * own WooCommerce product - inventing a second, catalog-owned price
 * here would risk silently diverging from the one that actually
 * charges the client.
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

        $hasSku = $this->productSku !== null && $this->productSku !== '';
        $hasSlug = $this->productSlug !== null && $this->productSlug !== '';

        if ($hasSku === $hasSlug) {
            throw new InvalidArgumentException(
                'Service must have exactly one of productSku or productSlug set.'
            );
        }
    }
}
