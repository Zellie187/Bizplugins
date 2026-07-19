<?php

declare(strict_types=1);

namespace BizHub\Dashboard\Widgets;

use BizHub\Companies\Services\CompanyLookupService;

/**
 * Dashboard widget summarizing a client's companies.
 *
 * @package BizHub\Dashboard\Widgets
 */
final class CompanyWidget implements Widget
{
    public function __construct(
        private readonly CompanyLookupService $companies
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        return 'companies';
    }

    /**
     * {@inheritDoc}
     */
    public function data(int $clientId, string $clientUuid): array
    {
        $summaries = $this->companies->search($clientId, '');

        return [
            'total' => count($summaries),
            'items' => array_map(
                static fn ($summary): array => $summary->toArray(),
                $summaries
            ),
        ];
    }
}
