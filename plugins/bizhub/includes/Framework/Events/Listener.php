<?php

declare(strict_types=1);

namespace BizHub\Framework\Events;

/**
 * Contract for event listeners.
 *
 * @package BizHub\Framework\Events
 */
interface Listener
{
    /**
     * Handle the dispatched event.
     */
    public function handle(Event $event): void;
}
