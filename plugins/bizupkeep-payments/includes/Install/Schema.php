<?php

declare(strict_types=1);

namespace BizHub\Payments\Install;

/**
 * Defines the database schema owned by BizUpKeep Payments.
 *
 * Table and column names here must exactly match the table/column
 * names used by Repositories\PaymentAttemptRepository. This class
 * only describes the schema; Migrator applies it via dbDelta().
 *
 * @package BizHub\Payments\Install
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
            'bizhub_payments_attempts' => "CREATE TABLE {$p}bizhub_payments_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                gateway VARCHAR(20) NOT NULL,
                gateway_reference VARCHAR(191) NULL,
                service_key VARCHAR(50) NOT NULL,
                company_uuid CHAR(36) NOT NULL,
                workflow_uuid CHAR(36) NULL,
                buyer_wp_user_id BIGINT UNSIGNED NOT NULL,
                amount_minor INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'ZAR',
                status VARCHAR(20) NOT NULL DEFAULT 'created',
                invoice_uuid CHAR(36) NULL,
                customer_uuid CHAR(36) NULL,
                failure_reason VARCHAR(255) NULL,
                fulfilled_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                confirmed_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uuid (uuid),
                KEY gateway_reference (gateway, gateway_reference),
                KEY workflow_uuid (workflow_uuid),
                KEY company_status (company_uuid, status)
            ) {$charsetCollate};",

            'bizhub_payments_webhook_log' => "CREATE TABLE {$p}bizhub_payments_webhook_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                gateway VARCHAR(20) NOT NULL,
                event_id VARCHAR(191) NOT NULL,
                payment_attempt_uuid CHAR(36) NULL,
                payload LONGTEXT NOT NULL,
                received_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY gateway_event (gateway, event_id)
            ) {$charsetCollate};",
        ];
    }
}
