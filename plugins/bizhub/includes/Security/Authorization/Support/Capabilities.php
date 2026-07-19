<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Support;

/**
 * BizHub
 *
 * Enterprise Business Management Platform
 *
 * Defines every business capability available within BizHub.
 *
 * Business modules should always reference these constants
 * instead of hardcoded capability strings or native
 * WordPress capabilities.
 *
 * @package BizHub
 * @subpackage Security\Authorization
 * @since 0.2.0
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

    public const APPLICATION_SUBMIT = 'application.submit';

    public const APPLICATION_CANCEL = 'application.cancel';

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

    public const CLIENT_LOGIN = 'client.login';

    public const CLIENT_PROFILE = 'client.profile';

    public const CLIENT_DOCUMENTS = 'client.documents';

    public const CLIENT_MESSAGES = 'client.messages';

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public const REPORT_VIEW = 'report.view';

    public const REPORT_EXPORT = 'report.export';

    /*
    |--------------------------------------------------------------------------
    | Administration
    |--------------------------------------------------------------------------
    */

    public const ADMIN_SETTINGS = 'admin.settings';

    public const ADMIN_USERS = 'admin.users';

    public const ADMIN_ROLES = 'admin.roles';

    public const ADMIN_AUDIT = 'admin.audit';

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }
}
