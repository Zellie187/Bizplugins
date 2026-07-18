<?php

declare(strict_types=1);

namespace BizHub\Security\Authorization\Services;

use BizHub\Security\Authorization\Contracts\PolicyInterface;

/**
 * Resolves policies for domain resources.
 *
 * @package BizHub\Security\Authorization\Services
 */
final class PolicyResolver
{
    /**
     * Registered policies.
     *
     * @var array<string,PolicyInterface>
     */
    private array $policies = [];

    /**
     * Register a policy.
     *
     * @param string          $resource
     * @param PolicyInterface $policy
     *
     * @return void
     */
    public function register(
        string $resource,
        PolicyInterface $policy
    ): void {
        $this->policies[$resource] = $policy;
    }

    /**
     * Determine whether a policy exists.
     *
     * @param string $resource
     *
     * @return bool
     */
    public function has(string $resource): bool
    {
        return isset($this->policies[$resource]);
    }

    /**
     * Resolve a policy.
     *
     * @param string $resource
     *
     * @return PolicyInterface|null
     */
    public function resolve(
        string $resource
    ): ?PolicyInterface {
        return $this->policies[$resource] ?? null;
    }

    /**
     * Return every registered policy.
     *
     * @return array<string,PolicyInterface>
     */
    public function all(): array
    {
        return $this->policies;
    }
}
