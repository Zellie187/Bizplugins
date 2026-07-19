<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Contracts;

use WP_User;

/**
 * Defines an authorization policy.
 *
 * Policies encapsulate business authorization rules
 * for a specific domain model.
 *
 * @package BizHub\Security\Authorization\Contracts
 */
interface PolicyInterface
{
    /**
     * Determine whether the given user
     * may perform the supplied ability.
     *
     * @param WP_User $user
     * @param string  $ability
     * @param mixed   $resource
     *
     * @return bool
     */
    public function authorize(
        WP_User $user,
        string $ability,
        mixed $resource = null
    ): bool;
}
