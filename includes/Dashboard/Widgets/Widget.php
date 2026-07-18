<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

/**
 * Contract for dashboard widgets.
 *
 * @package BizHub\Dashboard\Widgets
 */
interface Widget
{
    /**
     * Return the widget's unique key, used to identify it in the
     * assembled dashboard payload.
     */
    public function key(): string;

    /**
     * Build the widget's data for a given client.
     *
     * @return array<string,mixed>
     */
    public function data(int $clientId, string $clientUuid): array;
}
