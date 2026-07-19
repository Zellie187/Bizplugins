<?php

declare(strict_types=1);

namespace BizHub\ClientPortal\Entities;

use InvalidArgumentException;

/**
 * Represents a single client preference setting.
 *
 * @package BizHub\ClientPortal\Entities
 */
final readonly class Preference
{
    public function __construct(
        public string $clientUuid,
        public string $key,
        public string $value,
    ) {
        if ($this->clientUuid === '') {
            throw new InvalidArgumentException('Preference must be associated with a client.');
        }

        if (trim($this->key) === '') {
            throw new InvalidArgumentException('Preference key cannot be empty.');
        }
    }

    /**
     * Export entity as an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'client_uuid' => $this->clientUuid,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
