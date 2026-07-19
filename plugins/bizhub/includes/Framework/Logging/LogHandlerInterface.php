<?php

declare(strict_types=1);

namespace BizHub\Framework\Logging;

/**
 * Contract for log handlers.
 *
 * @package BizHub\Framework\Logging
 */
interface LogHandlerInterface
{
    /**
     * Write a log entry.
     *
     * @param string               $level   One of Logger's level constants.
     * @param string               $message
     * @param array<string,mixed>  $context
     */
    public function write(string $level, string $message, array $context = []): void;
}
