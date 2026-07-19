<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Tests\Unit\Install;

use BizUpKeep\Core\Install\Activator;
use PHPUnit\Framework\TestCase;

final class ActivatorTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BIZUPKEEP_CORE_VERSION')) {
            define('BIZUPKEEP_CORE_VERSION', '1.1.0-test');
        }

        $this->uploadDir = sys_get_temp_dir() . '/bizupkeep-core-tests-' . uniqid();

        $GLOBALS['__bizupkeep_core_test_options'] = [];
        $GLOBALS['__bizupkeep_core_test_upload_dir'] = ['basedir' => $this->uploadDir];
        $GLOBALS['__bizupkeep_core_test_rewrite_flushed'] = false;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->uploadDir);

        parent::tearDown();
    }

    public function testActivateCreatesUploadDirectories(): void
    {
        (new Activator())->activate();

        foreach (['', 'documents', 'logs', 'temp', 'exports'] as $subdirectory) {
            $expected = rtrim($this->uploadDir . '/bizupkeep/' . $subdirectory, '/');

            self::assertDirectoryExists($expected);
        }
    }

    public function testActivateStoresVersionAndInstallTimestamp(): void
    {
        (new Activator())->activate();

        self::assertSame('1.1.0-test', get_option('bizupkeep_core_version'));
        self::assertIsInt(get_option('bizupkeep_core_installed'));
    }

    public function testActivateFlushesRewriteRules(): void
    {
        (new Activator())->activate();

        self::assertTrue($GLOBALS['__bizupkeep_core_test_rewrite_flushed']);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
