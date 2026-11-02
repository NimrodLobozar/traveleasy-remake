<?php

declare(strict_types=1);

use App\Support\Health\HealthCheckFailed;
use App\Support\Health\SingleAttemptPostgresConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/*
| TE-012 AC5: `/up` is the container health check (docker/prod/healthcheck.php). It answers 200 only when the
| database answers, and its failure response leaks no connection details, SQL or stack trace.
*/

/**
 * Requests /up while the default connection points at a closed port, then restores the real default connection
 * before RefreshDatabase rolls back its transaction.
 *
 * @return TestResponse<Response>
 */
function healthWithUnreachableDatabase(TestCase $case, bool $json = false): TestResponse
{
    config([
        'app.debug' => false,
        'database.connections.unreachable' => array_merge(config()->array('database.connections.pgsql'), [
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => 'health_probe_user',
            'database' => 'health_probe_db',
        ]),
        'database.default' => 'unreachable',
    ]);

    try {
        return $json ? $case->getJson('/up') : $case->get('/up');
    } finally {
        config(['database.default' => 'pgsql']);
        DB::purge('unreachable');
    }
}

test('/up answers 200 when the database is reachable', function () {
    $this->get('/up')->assertOk();
    $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
});

test('/up answers 500 when the database is unreachable', function () {
    healthWithUnreachableDatabase($this)->assertStatus(500)->assertSee('experiencing problems');
});

test('/up answers 500 with only a status as JSON when the database is unreachable', function () {
    healthWithUnreachableDatabase($this, json: true)->assertStatus(500)->assertExactJson(['status' => 'down']);
});

test('the failed health response leaks no connection details, SQL or trace', function (bool $json) {
    $body = (string) healthWithUnreachableDatabase($this, $json)->getContent();

    expect($body)
        ->not->toContain('127.0.0.1')
        ->not->toContain('health_probe_user')
        ->not->toContain('health_probe_db')
        ->not->toContain('SQLSTATE')
        ->not->toContain('select 1')
        ->not->toContain('PDO')
        ->not->toContain('Health check failed')
        ->not->toContain('.php');
})->with(['html' => [false], 'json' => [true]]);

test('a failed health check logs one line without connection details and is not reported again', function () {
    Exceptions::fake();
    config(['logging.default' => 'null']);
    /** @var list<MessageLogged> $logged */
    $logged = [];
    Event::listen(function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event;
    });

    healthWithUnreachableDatabase($this)->assertStatus(500);

    expect($logged)->toHaveCount(1);
    $entry = $logged[0];
    $encoded = (string) json_encode($entry->context);

    expect($entry->level)->toBe('error')
        ->and($entry->message)->toBe('Health check failed: the database did not answer.')
        ->and(array_keys($entry->context))->toBe(['connection', 'exception', 'code'])
        ->and($entry->context['connection'])->toBe('unreachable')
        ->and($encoded)->not->toContain('127.0.0.1')->not->toContain('health_probe_user')->not->toContain('health_probe_db');
    Exceptions::assertNotReported(HealthCheckFailed::class);
});

test('the database health check listens to DiagnosingHealth', function () {
    expect(Event::hasListeners(DiagnosingHealth::class))->toBeTrue();
});

test('the health probe connects with a single attempt and a working database answers', function () {
    $config = config()->array('database.connections.pgsql');
    $config['database'] = DB::connection()->getDatabaseName();

    $statement = (new SingleAttemptPostgresConnector)->connect($config)->query('select 1');

    expect($statement)->toBeInstanceOf(PDOStatement::class)
        ->and($statement instanceof PDOStatement ? $statement->fetchColumn() : null)->toBe(1);
});

test('the health probe gives up after one failed attempt instead of retrying', function () {
    $config = array_merge(config()->array('database.connections.pgsql'), ['host' => '127.0.0.1', 'port' => 1]);

    $framework = new class extends PostgresConnector
    {
        public int $attempts = 0;

        /** @param  array<int, mixed>  $options */
        protected function createPdoConnection($dsn, $username, #[SensitiveParameter] $password, $options): PDO
        {
            $this->attempts++;

            return parent::createPdoConnection($dsn, $username, $password, $options);
        }
    };
    $probe = new class extends SingleAttemptPostgresConnector
    {
        public int $attempts = 0;

        /** @param  array<int, mixed>  $options */
        protected function createPdoConnection($dsn, $username, #[SensitiveParameter] $password, $options): PDO
        {
            $this->attempts++;

            return parent::createPdoConnection($dsn, $username, $password, $options);
        }
    };

    // The framework connector retries a refused connection once; the health probe must not.
    expect(fn () => $framework->connect($config))->toThrow(PDOException::class)
        ->and($framework->attempts)->toBe(2)
        ->and(fn () => $probe->connect($config))->toThrow(PDOException::class)
        ->and($probe->attempts)->toBe(1);
});
