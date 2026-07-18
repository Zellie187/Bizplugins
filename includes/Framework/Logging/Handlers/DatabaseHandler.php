<?php

declare(strict_types=1);

namespace BizHub\Framework\Logging\Handlers;

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Logging\LogHandlerInterface;

/**
 * Writes log entries to a database table.
 *
 * Assumes a table with (level, message, context, created_at) columns.
 * Table creation is the responsibility of the framework's install layer.
 *
 * @package BizHub\Framework\Logging\Handlers
 */
final class DatabaseHandler implements LogHandlerInterface
{
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly string $table = 'bizhub_logs'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $level, string $message, array $context = []): void
    {
        $this->database->insert($this->table, [
            'level' => $level,
            'message' => $message,
            'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
