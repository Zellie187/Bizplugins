<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Tests\Unit\Bootstrap;

use BizUpKeep\Core\Bootstrap\DependencyGuard;
use PHPUnit\Framework\TestCase;

final class DependencyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['__bizupkeep_core_test_options'] = [];
        $GLOBALS['__bizupkeep_core_test_hooks'] = [];
        $GLOBALS['__bizupkeep_core_bizhub'] = null;
    }

    public function testNotSatisfiedWhenBizHubPluginNeverLoaded(): void
    {
        self::assertFalse(DependencyGuard::bizhubPluginLoaded());
        self::assertFalse(DependencyGuard::satisfied());
    }

    public function testNotSatisfiedWhenBizHubLoadedButNotBooted(): void
    {
        if (! defined('BIZHUB_PLUGIN_FILE')) {
            define('BIZHUB_PLUGIN_FILE', __FILE__);
        }

        $GLOBALS['__bizupkeep_core_bizhub'] = null;

        self::assertTrue(DependencyGuard::bizhubPluginLoaded());
        self::assertFalse(DependencyGuard::bizhubReady());
        self::assertFalse(DependencyGuard::satisfied());
    }

    public function testSatisfiedWhenBizHubIsBooted(): void
    {
        if (! defined('BIZHUB_PLUGIN_FILE')) {
            define('BIZHUB_PLUGIN_FILE', __FILE__);
        }

        $GLOBALS['__bizupkeep_core_bizhub'] = new \stdClass();

        self::assertTrue(DependencyGuard::bizhubReady());
        self::assertTrue(DependencyGuard::satisfied());
    }

    public function testCheckAndNotifyRecordsProblemsWhenUnsatisfied(): void
    {
        $GLOBALS['__bizupkeep_core_bizhub'] = null;

        DependencyGuard::checkAndNotify();

        $problems = get_option('bizupkeep_core_dependency_notice', []);

        self::assertNotEmpty($problems);
    }

    public function testCheckAndNotifyClearsProblemsWhenSatisfied(): void
    {
        if (! defined('BIZHUB_PLUGIN_FILE')) {
            define('BIZHUB_PLUGIN_FILE', __FILE__);
        }

        $GLOBALS['__bizupkeep_core_bizhub'] = new \stdClass();
        update_option('bizupkeep_core_dependency_notice', ['stale problem']);

        DependencyGuard::checkAndNotify();

        self::assertSame(false, get_option('bizupkeep_core_dependency_notice', false));
    }
}
