<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\TestDatabase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boots the app pinned to pgsql / traveleasy_test and runs the database guard. This happens before any
     * trait setup, so before RefreshDatabase migrates anything. On a violation the whole run is aborted.
     */
    public function createApplication()
    {
        TestDatabase::forceEnvironment();

        $app = parent::createApplication();

        TestDatabase::forceConfiguration($app);

        $violation = TestDatabase::checkApplication($app);

        if ($violation !== null) {
            fwrite(STDERR, PHP_EOL.TestDatabase::abortMessage($violation));

            exit(1);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // PHP feature tests must not depend on a frontend build (public/build/manifest.json).
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
