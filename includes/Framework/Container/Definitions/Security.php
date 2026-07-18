<?php

declare(strict_types=1);

use BizHub\Security\Encryption\Encryptor;

return [

    Encryptor::class => DI\factory(static function (): Encryptor {
        return new Encryptor(wp_salt('auth'));
    }),

];
