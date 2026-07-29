<?php

declare(strict_types=1);

namespace BizHub\Bookkeeping\Install;

/**
 * Defines the database schema owned by BizUpKeep Bookkeeping.
 *
 * Table and column names here must exactly match the table/column
 * names used by the Accounts/AccountRepository and Ledger/JournalRepository
 * classes. This class only describes the schema; Migrator applies it via
 * dbDelta().
 *
 * @package BizHub\Bookkeeping\Install
 */
final class Schema
{
    /**
     * Return one dbDelta-compatible CREATE TABLE statement per table,
     * keyed by unprefixed table name.
     *
     * @return array<string,string>
     */
    public function statements(string $tablePrefix, string $charsetCollate): array
    {
        $p = $tablePrefix;

        return [
            'bizhub_bookkeeping_accounts' => "CREATE TABLE {$p}bizhub_bookkeeping_accounts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                code VARCHAR(10) NOT NULL,
                name VARCHAR(150) NOT NULL,
                account_type VARCHAR(20) NOT NULL,
                normal_balance VARCHAR(6) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_code (company_uuid, code),
                KEY company_uuid (company_uuid),
                KEY account_type (account_type)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_journal_entries' => "CREATE TABLE {$p}bizhub_bookkeeping_journal_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                entry_date DATE NOT NULL,
                description VARCHAR(500) NOT NULL DEFAULT '',
                source VARCHAR(30) NOT NULL,
                reversed_entry_uuid CHAR(36) NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY company_uuid (company_uuid),
                KEY company_date (company_uuid, entry_date),
                KEY reversed_entry_uuid (reversed_entry_uuid)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_journal_lines' => "CREATE TABLE {$p}bizhub_bookkeeping_journal_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entry_uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                entry_date DATE NOT NULL,
                account_uuid CHAR(36) NOT NULL,
                debit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                credit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                line_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
                memo VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY entry_uuid (entry_uuid),
                KEY account_uuid (account_uuid),
                KEY statement_lookup (company_uuid, account_uuid, entry_date)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_subscriptions' => "CREATE TABLE {$p}bizhub_bookkeeping_subscriptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                paid_until DATE NULL,
                is_suspended TINYINT(1) NOT NULL DEFAULT 0,
                last_reminder_type VARCHAR(20) NULL,
                last_reminder_for_paid_until DATE NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_uuid (company_uuid)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_import_mappings' => "CREATE TABLE {$p}bizhub_bookkeeping_import_mappings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                date_column VARCHAR(50) NOT NULL,
                description_column VARCHAR(50) NOT NULL,
                amount_style VARCHAR(20) NOT NULL,
                amount_column VARCHAR(50) NULL,
                debit_column VARCHAR(50) NULL,
                credit_column VARCHAR(50) NULL,
                date_format VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_uuid (company_uuid)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_staged_transactions' => "CREATE TABLE {$p}bizhub_bookkeeping_staged_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                source_account_uuid CHAR(36) NOT NULL,
                transaction_date DATE NOT NULL,
                description VARCHAR(500) NOT NULL DEFAULT '',
                amount_minor BIGINT NOT NULL,
                row_hash CHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                category_account_uuid CHAR(36) NULL,
                journal_entry_uuid CHAR(36) NULL,
                imported_at DATETIME NOT NULL,
                categorized_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_hash (company_uuid, row_hash),
                KEY company_status (company_uuid, status)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_company_settings' => "CREATE TABLE {$p}bizhub_bookkeeping_company_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                is_vat_registered TINYINT(1) NOT NULL DEFAULT 0,
                vat_number VARCHAR(20) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_uuid (company_uuid)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_recurring_templates' => "CREATE TABLE {$p}bizhub_bookkeeping_recurring_templates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                transaction_type VARCHAR(10) NOT NULL,
                amount_minor BIGINT UNSIGNED NOT NULL,
                category_account_uuid CHAR(36) NOT NULL,
                payment_method VARCHAR(10) NOT NULL,
                description VARCHAR(500) NOT NULL DEFAULT '',
                includes_vat TINYINT(1) NOT NULL DEFAULT 0,
                frequency VARCHAR(20) NOT NULL,
                next_due_date DATE NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY company_due (company_uuid, is_active, next_due_date)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_recurring_occurrences' => "CREATE TABLE {$p}bizhub_bookkeeping_recurring_occurrences (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                template_uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                due_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                journal_entry_uuid CHAR(36) NULL,
                generated_at DATETIME NOT NULL,
                resolved_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY template_due (template_uuid, due_date),
                KEY company_status (company_uuid, status)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_customers' => "CREATE TABLE {$p}bizhub_bookkeeping_customers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(50) NOT NULL DEFAULT '',
                address_line_1 VARCHAR(255) NOT NULL DEFAULT '',
                address_line_2 VARCHAR(255) NOT NULL DEFAULT '',
                suburb VARCHAR(100) NOT NULL DEFAULT '',
                city VARCHAR(100) NOT NULL DEFAULT '',
                province VARCHAR(100) NOT NULL DEFAULT '',
                postal_code VARCHAR(20) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY company_uuid (company_uuid)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_invoices' => "CREATE TABLE {$p}bizhub_bookkeeping_invoices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                customer_uuid CHAR(36) NOT NULL,
                invoice_number VARCHAR(20) NOT NULL,
                invoice_date DATE NOT NULL,
                due_date DATE NOT NULL,
                category_account_uuid CHAR(36) NOT NULL,
                includes_vat TINYINT(1) NOT NULL DEFAULT 0,
                subtotal_minor BIGINT UNSIGNED NOT NULL,
                vat_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                total_minor BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                notes VARCHAR(500) NOT NULL DEFAULT '',
                journal_entry_uuid CHAR(36) NULL,
                payment_journal_entry_uuid CHAR(36) NULL,
                sent_at DATETIME NULL,
                paid_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                UNIQUE KEY company_invoice_number (company_uuid, invoice_number),
                KEY company_uuid (company_uuid),
                KEY customer_uuid (customer_uuid),
                KEY company_status (company_uuid, status)
            ) {$charsetCollate};",

            'bizhub_bookkeeping_invoice_lines' => "CREATE TABLE {$p}bizhub_bookkeeping_invoice_lines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                invoice_uuid CHAR(36) NOT NULL,
                description VARCHAR(500) NOT NULL DEFAULT '',
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price_minor BIGINT UNSIGNED NOT NULL,
                line_total_minor BIGINT UNSIGNED NOT NULL,
                line_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY invoice_uuid (invoice_uuid)
            ) {$charsetCollate};",
        ];
    }
}
