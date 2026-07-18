<?php

declare(strict_types=1);

namespace BizHub\Integrations\WooCommerce;

use BizHub\Framework\Logging\Logger;
use WC_Order;

/**
 * Listens for WooCommerce order status changes and triggers BizHub
 * application creation for tracked products.
 *
 * @package BizHub\Integrations\WooCommerce
 */
final class OrderListener
{
    /**
     * Order statuses that should trigger application creation.
     *
     * @var array<int,string>
     */
    private const TRIGGER_STATUSES = ['processing', 'completed'];

    public function __construct(
        private readonly ApplicationCreator $applicationCreator,
        private readonly Logger $logger
    ) {
    }

    /**
     * Register WooCommerce hooks.
     */
    public function register(): void
    {
        add_action('woocommerce_order_status_changed', [$this, 'handleStatusChanged'], 10, 4);
    }

    /**
     * Handle the 'woocommerce_order_status_changed' hook.
     */
    public function handleStatusChanged(int $orderId, string $oldStatus, string $newStatus, WC_Order $order): void
    {
        if (! \in_array($newStatus, self::TRIGGER_STATUSES, true)) {
            return;
        }

        if ($order->get_meta('_bizhub_applications_created') === 'yes') {
            return;
        }

        $created = $this->applicationCreator->createFromOrder($order);

        if ($created === []) {
            return;
        }

        $order->update_meta_data('_bizhub_applications_created', 'yes');
        $order->save();

        $this->logger->info(
            sprintf('Created %d BizHub application(s) from WooCommerce order #%d.', count($created), $orderId),
            ['application_uuids' => $created]
        );
    }
}
