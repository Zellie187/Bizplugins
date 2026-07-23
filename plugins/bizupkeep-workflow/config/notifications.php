<?php

declare(strict_types=1);

/**
 * Notification templates for workflow transitions.
 *
 * Keyed by workflow type, then by action name. {placeholders} are
 * substituted from the WorkflowInstance and Transition at dispatch
 * time by BizHub\Workflow\Notifications\WorkflowNotificationListener.
 * An action with no entry here simply raises no notification.
 *
 * @return array<string,array<string,array{subject:string,body:string}>>
 */
return [

    \BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition::TYPE => [

        'request_documents' => [
            'subject' => 'Documents required for your company registration',
            'body' => 'Your company registration ({workflow_uuid}) now requires supporting documents to be uploaded before it can proceed.',
        ],

        'verify_documents' => [
            'subject' => 'Documents verified',
            'body' => 'The documents for your company registration ({workflow_uuid}) have been verified.',
        ],

        'request_payment' => [
            'subject' => 'Payment required to continue your company registration',
            'body' => 'Your company registration ({workflow_uuid}) is ready for payment.',
        ],

        'confirm_payment' => [
            'subject' => 'Payment received',
            'body' => 'Payment has been received for your company registration ({workflow_uuid}). Processing has begun.',
        ],

        'approve' => [
            'subject' => 'Company registration completed',
            'body' => 'Your company registration ({workflow_uuid}) has been completed.',
        ],

        'cancel' => [
            'subject' => 'Company registration cancelled',
            'body' => 'Your company registration ({workflow_uuid}) has been cancelled. Reason: {reason}',
        ],

        'reject' => [
            'subject' => 'Company registration rejected',
            'body' => 'Your company registration ({workflow_uuid}) was rejected during quality review. Reason: {reason}',
        ],

        'reject_name' => [
            'subject' => 'Name not approved - action required',
            'body' => 'CIPC did not approve the proposed name(s) for your company registration '
                . '({workflow_uuid}). Please log in and submit new options. Reason: {reason}',
        ],

    ],

    \BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition::TYPE => [

        'request_documents' => [
            'subject' => 'Documents required for your company amendment',
            'body' => 'Your company amendment ({workflow_uuid}) now requires supporting documents '
                . 'to be uploaded before it can proceed.',
        ],

        'verify_documents' => [
            'subject' => 'Documents verified',
            'body' => 'The documents for your company amendment ({workflow_uuid}) have been verified.',
        ],

        'request_payment' => [
            'subject' => 'Payment required to continue your company amendment',
            'body' => 'Your company amendment ({workflow_uuid}) is ready for payment.',
        ],

        'confirm_payment' => [
            'subject' => 'Payment received',
            'body' => 'Payment has been received for your company amendment ({workflow_uuid}). Processing has begun.',
        ],

        'approve' => [
            'subject' => 'Company amendment completed',
            'body' => 'Your company amendment ({workflow_uuid}) has been completed.',
        ],

        'cancel' => [
            'subject' => 'Company amendment cancelled',
            'body' => 'Your company amendment ({workflow_uuid}) has been cancelled. Reason: {reason}',
        ],

        'reject' => [
            'subject' => 'Company amendment rejected',
            'body' => 'Your company amendment ({workflow_uuid}) was rejected during quality review. Reason: {reason}',
        ],

        'reject_name' => [
            'subject' => 'Name not approved - action required',
            'body' => 'CIPC did not approve the proposed name(s) for your company amendment '
                . '({workflow_uuid}). Please log in and submit new options. Reason: {reason}',
        ],

    ],

    \BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition::TYPE => [

        'request_payment' => [
            'subject' => 'Your annual return filing has been quoted',
            'body' => 'Your annual return filing ({workflow_uuid}) has been quoted at R{quote_amount}. '
                . 'Please log in to review and pay.',
        ],

        'revise_quote' => [
            'subject' => 'Your annual return quote has been revised',
            'body' => 'The quote for your annual return filing ({workflow_uuid}) has been revised '
                . 'to R{quote_amount}. Please log in to review and pay.',
        ],

        'confirm_payment' => [
            'subject' => 'Payment received',
            'body' => 'Payment has been received for your annual return filing ({workflow_uuid}). '
                . 'Processing has begun.',
        ],

        'approve' => [
            'subject' => 'Annual return filing completed',
            'body' => 'Your annual return filing ({workflow_uuid}) has been completed.',
        ],

        'cancel' => [
            'subject' => 'Annual return filing cancelled',
            'body' => 'Your annual return filing ({workflow_uuid}) has been cancelled. Reason: {reason}',
        ],

    ],

];
