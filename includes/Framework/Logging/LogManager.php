<?php

declare(strict_types=1);

namespace BizHub\Framework\Logging;

/**
 * Manages the set of active log handlers.
 *
 * @package BizHub\Framework\Logging
 */
final class LogManager
{
    /**
     * @var array<int,LogHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Register a log handler.
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Return every registered handler.
     *
     * @return array<int,LogHandlerInterface>
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * Write a log entry to every registered handler.
     *
     * @param array<string,mixed> $context
     */
    public function write(string $level, string $message, array $context = []): void
    {
        foreach ($this->handlers as $handler) {
            $handler->write($level, $message, $context);
        }
    }
}
