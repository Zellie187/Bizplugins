<?php

declare(strict_types=1);

use BizHub\Framework\Database\Contracts\DatabaseInterface;
use BizHub\Framework\Database\Contracts\TransactionInterface;
use BizHub\Framework\Database\Drivers\WordPressDatabase;
use BizHub\Framework\Database\Transactions\Transaction;

return [

    wpdb::class => DI\factory(static function () {
        global $wpdb;

        return $wpdb;
    }),

    DatabaseInterface::class => DI\autowire(WordPressDatabase::class),

    TransactionInterface::class => DI\autowire(Transaction::class),

];
