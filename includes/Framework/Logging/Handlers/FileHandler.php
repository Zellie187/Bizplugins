<?php

declare(strict_types=1);

namespace BizHub\Framework\Logging\Handlers;

use BizHub\Framework\Logging\LogHandlerInterface;

/**
 * Writes log entries to a flat file.
 *
 * @package BizHub\Framework\Logging\Handlers
 */
final class FileHandler implements LogHandlerInterface
{
    public function __construct(
        private readonly string $path
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $level, string $message, array $context = []): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $line = sprintf(
            '[%s] %s: %s%s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES)
        );

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
