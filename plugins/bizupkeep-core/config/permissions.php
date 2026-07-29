<?php

declare(strict_types=1);

use BizUpKeep\Core\Policies\Capabilities;

/**
 * Maps WordPress/BizHub roles to the BizUpKeep Core capabilities
 * granted to them at activation (see BizUpKeep\Core\Install\RoleGrant).
 *
 * "administrator" is WordPress's native role; the "bizhub_*" roles are
 * BizHub's own, granted here only where BizHub has already registered
 * them. The Service catalog is staff-only (there is no client-facing
 * capability yet), so every granted role gets the full set.
 *
 * @return array<string,array<int,string>>
 */
return [

    'administrator' => Capabilities::all(),

    'bizhub_administrator' => Capabilities::all(),

    'bizhub_manager' => Capabilities::all(),

    'bizhub_staff' => Capabilities::all(),

];
