<?php

declare(strict_types=1);

namespace BizHub\Api\Resources;

use BizHub\Applications\Entities\Application;

/**
 * Transforms Application entities into their REST API representation.
 *
 * @package BizHub\Api\Resources
 */
final class ApplicationResource
{
    /**
     * Transform a single application.
     *
     * @return array<string,mixed>
     */
    public static function make(Application $application): array
    {
        return $application->toArray();
    }

    /**
     * Transform a collection of applications.
     *
     * @param Application[] $applications
     *
     * @return array<int,array<string,mixed>>
     */
    public static function collection(array $applications): array
    {
        return array_map([self::class, 'make'], $applications);
    }
}
