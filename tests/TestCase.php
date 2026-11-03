<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Testing\ParallelTesting;
use Laravel\Fortify\Features;
use Tests\Support\BuildlessVite;
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

        // PHP tests must not depend on a frontend build (public/build/manifest.json) or on a running Vite dev
        // server (public/hot): CI runs Pest before the client build, and dev machines have both.
        $this->withoutVite();
    }

    /**
     * Swaps in Tests\Support\BuildlessVite: no tags, no manifest, never "hot". Laravel's own double still reads
     * public/hot. withVite() restores the real Vite for the few tests that set one up explicitly.
     */
    protected function withoutVite()
    {
        parent::withoutVite();

        ViteFacade::clearResolvedInstance();
        $this->swap(Vite::class, new BuildlessVite);

        return $this;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
