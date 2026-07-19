<?php

declare(strict_types=1);

namespace BizHub\Integrations\Forminator;

use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\DTO\ApplicationData;
use BizHub\Applications\Entities\Application;
use BizHub\Framework\Support\Uuid;

/**
 * Generates BizHub applications from validated Forminator submission data.
 *
 * @package BizHub\Integrations\Forminator
 */
final class ApplicationGenerator
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications
    ) {
    }

    /**
     * Create an application from validated field data.
     *
     * @param array<string,mixed> $fields Validated fields (see Validation).
     */
    public function generate(string $applicationType, array $fields): Application
    {
        return $this->applications->createApplication(new ApplicationData(
            Uuid::generate(),
            (int) $fields['client_id'],
            $applicationType
        ));
    }
}
