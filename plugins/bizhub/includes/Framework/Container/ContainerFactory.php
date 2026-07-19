<?php

declare(strict_types=1);

namespace BizHub\Framework\Container;

use DI\Container;
use DI\ContainerBuilder;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Creates the application's dependency injection container.
 *
 * @package BizHub\Framework\Container
 */
final class ContainerFactory
{
    /**
     * Create and configure the DI container.
     */
    public static function create(): Container
    {
        $builder = new ContainerBuilder();

        /*
         * Future production configuration:
         *
         * - Enable compilation
         * - Enable definition caching
         * - Environment-specific configuration
         */
        // if (! defined('WP_DEBUG') || WP_DEBUG === false) {
        //     $builder->enableCompilation(BIZHUB_PLUGIN_PATH . 'cache/container');
        // }

        $builder->addDefinitions(
            __DIR__ . '/definitions.php'
        );

        foreach (self::discoverDefinitionFiles() as $file) {
            $builder->addDefinitions($file);
        }

        foreach (self::externalDefinitions() as $definitions) {
            $builder->addDefinitions($definitions);
        }

        return $builder->build();
    }

    /**
     * Allow external plugins built on top of BizHub (e.g. BizUpKeep
     * Workflow) to contribute their own PHP-DI definitions into the
     * shared container, instead of building a second container.
     *
     * Callbacks must run before this method executes, i.e. during
     * plugin file inclusion (before 'plugins_loaded'), since the
     * container is created from within a 'plugins_loaded' callback.
     *
     * Each entry may be either a path to a definitions file (as
     * accepted by ContainerBuilder::addDefinitions()) or a
     * `array<string,mixed>` of raw definitions.
     *
     * @return array<int,string|array<string,mixed>>
     */
    private static function externalDefinitions(): array
    {
        /** @var array<int,string|array<string,mixed>> $definitions */
        $definitions = apply_filters('bizhub/container_definitions', []);

        return $definitions;
    }

    /**
     * Discover every additional container definitions file under
     * includes/, excluding the central definitions.php already loaded.
     *
     * @return array<int,string>
     */
    private static function discoverDefinitionFiles(): array
    {
        $root = \dirname(__DIR__, 2);
        $central = __DIR__ . '/definitions.php';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        $files = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();

            if ($path === $central) {
                continue;
            }

            $isModuleDefinitions = $fileInfo->getFilename() === 'definitions.php';
            $containerFragmentPath = DIRECTORY_SEPARATOR . 'Container' . DIRECTORY_SEPARATOR
                . 'Definitions' . DIRECTORY_SEPARATOR;
            $isContainerFragment = \str_contains($path, $containerFragmentPath);

            if (! $isModuleDefinitions && ! $isContainerFragment) {
                continue;
            }

            if (\trim((string) \file_get_contents($path)) === '') {
                continue;
            }

            $files[] = $path;
        }

        \sort($files);

        return $files;
    }
}
