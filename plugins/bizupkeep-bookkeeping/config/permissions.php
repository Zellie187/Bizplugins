<?php

declare(strict_types=1);

use BizHub\Bookkeeping\Policies\Capabilities;

/**
 * Maps WordPress/BizHub roles to the BizUpKeep Bookkeeping capabilities
 * granted to them at activation (see BizHub\Bookkeeping\Install\RoleGrant).
 *
 * "administrator" is WordPress's native role; the "bizhub_*" roles are
 * BizHub's own, granted here only where BizHub has already registered
 * them. Unlike BizUpKeep Workflow, Bookkeeping has capabilities a
 * *client* needs (their own view/capture/export), so bizhub_client is
 * granted a deliberately restricted subset here - custom chart-of-accounts
 * edits and manual multi-line journal entries stay staff/admin-only.
 *
 * @return array<string,array<int,string>>
 */
return [

    'administrator' => Capabilities::all(),

    'bizhub_administrator' => Capabilities::all(),

    'bizhub_manager' => Capabilities::all(),

    'bizhub_staff' => Capabilities::all(),

    'bizhub_client' => [
        Capabilities::BOOKKEEPING_VIEW,
        Capabilities::BOOKKEEPING_CAPTURE,
        Capabilities::BOOKKEEPING_EXPORT,
    ],

];
