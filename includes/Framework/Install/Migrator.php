<?php

declare(strict_types=1);

namespace BizHub\Framework\Install;

use wpdb;

/**
 * Applies the BizHub database schema using WordPress's dbDelta().
 *
 * dbDelta() is idempotent: it inspects each table's current structure
 * and only applies the differences, so this is safe to run on every
 * activation and on every version upgrade, not just first install.
 *
 * @package BizHub\Framework\Install
 */
final class Migrator
{
    private const VERSION_OPTION = 'bizhub_db_version';

    /**
     * Must be bumped whenever Schema's table definitions change, so
     * that Upgrader knows a migration is needed.
     */
    public const CURRENT_VERSION = '1.0.0';

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly Schema $schema
    ) {
    }

    /**
     * Create or update every BizHub table to match the current schema.
     */
    public function migrate(): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charsetCollate = $this->wpdb->get_charset_collate();

        foreach ($this->schema->statements($this->wpdb->prefix, $charsetCollate) as $sql) {
            dbDelta($sql);
        }

        update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
    }

    /**
     * Return the schema version currently recorded in the database, or
     * null if BizHub has never installed its tables.
     */
    public function installedVersion(): ?string
    {
        $version = get_option(self::VERSION_OPTION, null);

        return \is_string($version) ? $version : null;
    }

    /**
     * Determine whether the installed schema is out of date.
     */
    public function needsMigration(): bool
    {
        return $this->installedVersion() !== self::CURRENT_VERSION;
    }
}
