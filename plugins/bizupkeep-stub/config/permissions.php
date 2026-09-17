<?php

declare(strict_types=1);

use BizHub\Stub\Policies\Capabilities;

/**
 * Maps WordPress/BizHub roles to the BizUpKeep Stub capabilities
 * granted to them at activation (see BizHub\Stub\Install\RoleGrant).
 *
 * @return array<string,array<int,string>>
 */
return [

    'administrator' => Capabilities::all(),

    'bizhub_administrator' => Capabilities::all(),

    'bizhub_manager' => Capabilities::all(),

];
