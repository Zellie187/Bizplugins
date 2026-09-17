<?php

declare(strict_types=1);

namespace BizHub\Stub\Install;

use wpdb;

/**
 * Applies the BizUpKeep Stub database schema using WordPress's
 * dbDelta(). Idempotent: safe to run on every activation and on every
 * version upgrade.
 *
 * @package BizHub\Stub\Install
 */
final class Migrator
{
    private const VERSION_OPTION = 'bizupkeep_stub_db_version';

    /**
     * Must be bumped whenever Schema's table definitions change.
     */
    public const CURRENT_VERSION = '1.0.0';

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly Schema $schema
    ) {
    }

    public function migrate(): void
    {
        if (! \function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charsetCollate = $this->wpdb->get_charset_collate();

        foreach ($this->schema->statements($this->wpdb->prefix, $charsetCollate) as $sql) {
            dbDelta($sql);
        }

        update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
    }

    public function installedVersion(): ?string
    {
        $version = get_option(self::VERSION_OPTION, null);

        return \is_string($version) ? $version : null;
    }

    public function needsMigration(): bool
    {
        return $this->installedVersion() !== self::CURRENT_VERSION;
    }
}
