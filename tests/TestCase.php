<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\ParallelTesting;
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

        self::abortOn(TestDatabase::checkApplication($app));

        // Parallel runs only (Laravel skips these callbacks otherwise). Registered after the framework's own
        // callback, so it runs after the switch to traveleasy_test_test_{token} and before RefreshDatabase.
        $app->make(ParallelTesting::class)->setUpTestCase(
            function (mixed $token) use ($app): void {
                self::abortOn(TestDatabase::checkParallelWorker($app, $token));
            },
        );

        return $app;
    }

    private static function abortOn(?string $violation): void
    {
        if ($violation !== null) {
            fwrite(STDERR, PHP_EOL.TestDatabase::abortMessage($violation));

            exit(1);
        }
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
