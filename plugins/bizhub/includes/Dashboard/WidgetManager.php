<?php

declare(strict_types=1);

namespace BizHub\Dashboard;

use BizHub\Dashboard\Widgets\Widget;

/**
 * Registers and executes dashboard widgets.
 *
 * @package BizHub\Dashboard
 */
final class WidgetManager
{
    /**
     * @var array<int,Widget>
     */
    private array $widgets = [];

    /**
     * Register a widget.
     */
    public function register(Widget $widget): void
    {
        $this->widgets[] = $widget;
    }

    /**
     * Build the data payload for every registered widget.
     *
     * @return array<string,array<string,mixed>>
     */
    public function buildAll(int $clientId, string $clientUuid): array
    {
        $payload = [];

        foreach ($this->widgets as $widget) {
            $payload[$widget->key()] = $widget->data($clientId, $clientUuid);
        }

        return $payload;
    }
}
