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
| TE-028 AC3: the body is plain text without any external reference (the framework's page linked fonts.bunny.net and
| jsdelivr, whose preconnects reveal the visitor's IP).
*/

/**
 * Requests /up while the default connection points at a closed port, then restores the real default connection
 * before RefreshDatabase rolls back its transaction.
 *
 * @return TestResponse<Response>
 */
function healthWithUnreachableDatabase(TestCase $case, bool $json = false, bool $debug = false): TestResponse
{
    config([
        'app.debug' => $debug,
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

test('/up answers 200 with a plain-text OK when the database is reachable', function () {
    $response = $this->get('/up')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())->toBe('OK');
});

test('/up answers a generic plain-text 500 when the database is unreachable', function (bool $json) {
    $response = healthWithUnreachableDatabase($this, $json)
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())->toBe('Unavailable');
})->with(['html client' => [false], 'json client' => [true]]);

test('/up is not cached and sets no cookies', function () {
    $response = $this->get('/up');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->getCookies())->toBe([]);
});

test('the /up body references no external host, healthy or not', function (bool $healthy) {
    $body = (string) ($healthy ? $this->get('/up') : healthWithUnreachableDatabase($this))->getContent();

    expect($body)
        ->not->toMatch('#https?://#i')
        ->not->toMatch('#//[a-z0-9.-]+\.[a-z]{2,}#i')
        ->not->toContain('bunny')
        ->not->toContain('jsdelivr')
        ->not->toContain('<');
})->with(['healthy' => [true], 'database down' => [false]]);

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

test('debug mode does not turn a failed health check into a debug page', function () {
    $response = healthWithUnreachableDatabase($this, debug: true)->assertStatus(500);

    expect($response->getContent())->toBe('Unavailable');
});

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
