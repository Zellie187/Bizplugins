<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Entities;

/**
 * Client account status.
 *
 * @package BizHub\ClientPortal\Entities
 */
enum ClientStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    /**
     * Determine whether the client can access the portal.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }
}
