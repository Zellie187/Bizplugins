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

        return $builder->build();
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
