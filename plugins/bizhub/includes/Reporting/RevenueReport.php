<?php

declare(strict_types=1);

namespace BizHub\Reporting;

/**
 * Aggregates revenue statistics from WooCommerce orders.
 *
 * Returns zeroed results when WooCommerce is not active.
 *
 * @package BizHub\Reporting
 */
final class RevenueReport
{
    /**
     * Return total revenue between two dates (inclusive), in the
     * store's default currency.
     */
    public function totalBetween(string $from, string $to): float
    {
        if (! function_exists('wc_get_orders')) {
            return 0.0;
        }

        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'date_created' => $from . '...' . $to,
            'limit' => -1,
        ]);

        $total = 0.0;

        foreach ($orders as $order) {
            $total += (float) $order->get_total();
        }

        return round($total, 2);
    }

    /**
     * Return the number of completed/processing orders between two dates.
     */
    public function orderCountBetween(string $from, string $to): int
    {
        if (! function_exists('wc_get_orders')) {
            return 0;
        }

        return count(wc_get_orders([
            'status' => ['completed', 'processing'],
            'date_created' => $from . '...' . $to,
            'limit' => -1,
        ]));
    }
}
