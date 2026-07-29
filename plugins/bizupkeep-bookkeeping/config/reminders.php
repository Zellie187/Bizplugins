<?php

declare(strict_types=1);

/**
 * Subscription reminder email templates, keyed by reminder type.
 * {placeholders} are substituted by
 * BizHub\Bookkeeping\Billing\SubscriptionReminderService at dispatch
 * time. The renew link deliberately points at the general "My
 * Bookkeeping" page rather than a direct pay-now deep link - the
 * client clicks the existing "Subscribe Now" button there themselves,
 * so this plugin never has to duplicate the theme's URL-building logic
 * for its own routing scheme.
 *
 * @return array<string,array{subject:string,body:string}>
 */
return [

    'expiring_soon' => [
        'subject' => 'Your bookkeeping subscription expires soon',
        'body' => 'The bookkeeping subscription for {company_name} expires on {paid_until}. '
            . 'Renew before then to keep capturing transactions and exporting your books without interruption: '
            . '{renew_url}',
    ],

    'lapsed' => [
        'subject' => 'Your bookkeeping subscription has expired',
        'body' => 'The bookkeeping subscription for {company_name} expired on {paid_until}. '
            . 'Capturing new transactions and exporting your books is paused until you renew - '
            . 'your existing records are safe and still viewable. Renew here: {renew_url}',
    ],

];
