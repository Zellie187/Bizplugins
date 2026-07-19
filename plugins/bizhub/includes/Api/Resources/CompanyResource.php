<?php

declare(strict_types=1);

namespace BizHub\Api\Resources;

use BizHub\Companies\Entities\Company;

/**
 * Transforms Company entities into their REST API representation.
 *
 * @package BizHub\Api\Resources
 */
final class CompanyResource
{
    /**
     * Transform a single company.
     *
     * @return array<string,mixed>
     */
    public static function make(Company $company): array
    {
        return $company->toArray();
    }

    /**
     * Transform a collection of companies.
     *
     * @param Company[] $companies
     *
     * @return array<int,array<string,mixed>>
     */
    public static function collection(array $companies): array
    {
        return array_map([self::class, 'make'], $companies);
    }
}
