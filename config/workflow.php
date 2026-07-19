<?php

declare(strict_types=1);

use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;

/**
 * Registry of workflow types shipped with BizUpKeep Workflow.
 *
 * This is a documentation/reference config, not a wiring mechanism:
 * each entry's actual registration into the workflow engine happens
 * in its own Service Provider (see bizupkeep-workflow.php's
 * 'bizhub/register_providers' callback), because registration needs
 * live service instances (the definition, its guard) that a plain
 * config array cannot hold.
 *
 * @return array<string,array{definition:class-string,label:string}>
 */
return [

    CompanyRegistrationDefinition::TYPE => [
        'definition' => CompanyRegistrationDefinition::class,
        'label' => 'Company Registration',
    ],

];
