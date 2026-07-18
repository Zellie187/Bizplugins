<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\DTO\ApplicationData;
use BizHub\Framework\Support\Uuid;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Creates BizHub applications from completed WooCommerce orders.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class ApplicationCreator
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly ProductMapper $productMapper,
        private readonly CustomerSynchronizer $customers
    ) {
    }

    /**
     * Create an application for every tracked product in an order.
     *
     * @return array<int,string> UUIDs of the applications created.
     */
    public function createFromOrder(WC_Order $order): array
    {
        $wpUserId = $order->get_customer_id();

        if ($wpUserId <= 0) {
            return [];
        }

        $client = $this->customers->syncFromWpUser($wpUserId);

        $created = [];

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $applicationType = $this->productMapper->applicationTypeForProduct($item->get_product_id());

            if ($applicationType === null) {
                continue;
            }

            $application = $this->applications->createApplication(new ApplicationData(
                Uuid::generate(),
                $client->getWpUserId(),
                $applicationType
            ));

            $created[] = $application->getUuid();
        }

        return $created;
    }
}
