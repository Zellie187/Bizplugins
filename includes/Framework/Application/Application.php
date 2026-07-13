<?php

declare(strict_types=1);

namespace BizHub\Framework\Application;

use BizHub\Framework\Container\Container;
use BizHub\Framework\Contracts\ContainerInterface;

/**
 * Main BizHub application.
 *
 * @package BizHub\Framework\Application
 */
final class Application
{
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Service container.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Framework kernel.
     *
     * @var Kernel
     */
    private Kernel $kernel;

    /**
     * Boot status.
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->container = new Container();

        $this->container->singleton(
            ContainerInterface::class,
            $this->container
        );

        $this->kernel = new Kernel($this);
    }

    /**
     * Retrieve application instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot application.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->kernel->boot();

        $this->booted = true;
    }

    /**
     * Retrieve container.
     *
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Retrieve kernel.
     *
     * @return Kernel
     */
    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * Retrieve framework version.
     *
     * @return string
     */
    public function version(): string
    {
        return BIZHUB_VERSION;
    }

    /**
     * Retrieve plugin path.
     *
     * @return string
     */
    public function basePath(): string
    {
        return BIZHUB_PLUGIN_PATH;
    }
}