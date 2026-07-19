<?php

declare(strict_types=1);

use BizHub\Workflow\Policies\Capabilities;

/**
 * Maps WordPress/BizHub roles to the BizUpKeep Workflow capabilities
 * granted to them at activation (see BizHub\Workflow\Install\RoleGrant).
 *
 * "administrator" is WordPress's native role; the "bizhub_*" roles are
 * BizHub's own, granted here only where BizHub has already registered
 * them.
 *
 * @return array<string,array<int,string>>
 */
return [

    'administrator' => Capabilities::all(),

    'bizhub_administrator' => Capabilities::all(),

    'bizhub_manager' => Capabilities::all(),

    'bizhub_staff' => [
        Capabilities::WORKFLOW_VIEW,
        Capabilities::WORKFLOW_TRANSITION,
    ],

];
