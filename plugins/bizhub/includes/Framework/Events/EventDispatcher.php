<?php

declare(strict_types=1);

namespace BizHub\Framework\Events;

/**
 * Dispatches events to their registered listeners.
 *
 * @package BizHub\Framework\Events
 */
final class EventDispatcher
{
    /**
     * @var array<class-string<Event>,array<int,Listener|callable(Event):void>>
     */
    private array $listeners = [];

    /**
     * Register a listener for an event class.
     *
     * @param class-string<Event>            $eventClass
     * @param Listener|callable(Event):void  $listener
     */
    public function listen(string $eventClass, Listener|callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Determine whether an event class has any registered listeners.
     *
     * @param class-string<Event> $eventClass
     */
    public function hasListeners(string $eventClass): bool
    {
        return ! empty($this->listeners[$eventClass]);
    }

    /**
     * Dispatch an event to all of its registered listeners.
     */
    public function dispatch(Event $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            if ($listener instanceof Listener) {
                $listener->handle($event);

                continue;
            }

            $listener($event);
        }
    }
}
