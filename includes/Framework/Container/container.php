<?php

declare(strict_types=1);

namespace BizHub\Framework\Container;

use BizHub\Framework\Container\Exceptions\ContainerException;
use BizHub\Framework\Contracts\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * BizHub dependency injection container.
 *
 * Provides service registration and automatic dependency resolution.
 *
 * @package BizHub\Framework\Container
 */
final class Container implements ContainerInterface
{
    /**
     * Registered bindings.
     *
     * @var array<string, Binding>
     */
    private array $bindings = [];

    /**
     * Resolved shared instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Register a binding.
     *
     * @param string $abstract Service identifier.
     * @param mixed  $concrete Service implementation.
     * @param bool   $shared   Whether the instance is shared.
     *
     * @return void
     */
    public function bind(
        string $abstract,
        mixed $concrete,
        bool $shared = false
    ): void {
        $this->bindings[$abstract] = new Binding(
            $concrete,
            $shared
        );
    }

    /**
     * Register a singleton binding.
     *
     * @param string $abstract Service identifier.
     * @param mixed  $concrete Service implementation.
     *
     * @return void
     */
    public function singleton(
        string $abstract,
        mixed $concrete
    ): void {
        $this->bind(
            $abstract,
            $concrete,
            true
        );
    }

    /**
     * Resolve a service.
     *
     * @param string $abstract Service identifier.
     *
     * @throws ContainerException When resolution fails.
     *
     * @return mixed
     */
    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];

            $object = $this->build(
                $binding->concrete()
            );

            if ($binding->isShared()) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        return $this->build($abstract);
    }

    /**
     * Determine if a service exists.
     *
     * @param string $abstract Service identifier.
     *
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract]);
    }

    /**
     * Build a concrete implementation.
     *
     * @param mixed $concrete Concrete definition.
     *
     * @throws ContainerException
     *
     * @return mixed
     */
    private function build(mixed $concrete): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        if (! is_string($concrete)) {
            return $concrete;
        }

        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $exception) {
            throw new ContainerException(
                sprintf(
                    'Unable to reflect class %s.',
                    $concrete
                ),
                0,
                $exception
            );
        }

        if (! $reflection->isInstantiable()) {
            throw new ContainerException(
                sprintf(
                    'Class %s is not instantiable.',
                    $concrete
                )
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (
                ! $type instanceof ReflectionNamedType
                || $type->isBuiltin()
            ) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();

                    continue;
                }

                throw new ContainerException(
                    sprintf(
                        'Unable to resolve parameter %s.',
                        $parameter->getName()
                    )
                );
            }

            $dependencies[] = $this->make(
                $type->getName()
            );
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}