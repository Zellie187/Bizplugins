<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

use BizHub\Applications\Contracts\ApplicationServiceInterface;

/**
 * Dashboard widget summarizing a client's applications.
 *
 * @package BizHub\Dashboard\Widgets
 */
final class ApplicationWidget implements Widget
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return 'applications';
    }

    /**
     * {@inheritDoc}
     */
    public function data(int $clientId, string $clientUuid): array
    {
        $summaries = $this->applications->getApplicationSummaries($clientId);

        return [
            'total' => count($summaries),
            'items' => array_map(
                static fn ($summary): array => $summary->toArray(),
                $summaries
            ),
        ];
    }
}
