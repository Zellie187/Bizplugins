<?php

declare(strict_types=1);

namespace BizHub\Platform\Authorization\Support;

/**
 * BizHub Capability Registry.
 *
 * This class contains every platform capability.
 * Modules should reference these constants instead of
 * using hard-coded capability strings.
 *
 * @package BizHub\Platform\Authorization\Support
 */
final class Capabilities
{
    /*
    |--------------------------------------------------------------------------
    | Companies
    |--------------------------------------------------------------------------
    */

    public const COMPANY_VIEW = 'company.view';

    public const COMPANY_CREATE = 'company.create';

    public const COMPANY_EDIT = 'company.edit';

    public const COMPANY_DELETE = 'company.delete';

    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    */

    public const APPLICATION_VIEW = 'application.view';

    public const APPLICATION_CREATE = 'application.create';

    public const APPLICATION_EDIT = 'application.edit';

    public const APPLICATION_APPROVE = 'application.approve';

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public const DOCUMENT_VIEW = 'document.view';

    public const DOCUMENT_UPLOAD = 'document.upload';

    public const DOCUMENT_DOWNLOAD = 'document.download';

    public const DOCUMENT_DELETE = 'document.delete';

    /*
    |--------------------------------------------------------------------------
    | Client Portal
    |--------------------------------------------------------------------------
    */

    public const PORTAL_ACCESS = 'portal.access';

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    public const WORKFLOW_VIEW = 'workflow.view';

    public const WORKFLOW_MANAGE = 'workflow.manage';

    /*
    |--------------------------------------------------------------------------
    | Administration
    |--------------------------------------------------------------------------
    */

    public const SETTINGS_MANAGE = 'settings.manage';

    public const USERS_MANAGE = 'users.manage';
}