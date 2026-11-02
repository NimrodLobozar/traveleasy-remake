<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Listens to DiagnosingHealth, so `/up` (the container health check, ADR-0002) answers 200 only when the default
 * database connection answers a query (TE-012 AC5). For PostgreSQL it opens one fresh connection with a single attempt
 * (SingleAttemptPostgresConnector), so a database that went away fails the probe quickly instead of after retries.
 *
 * On failure it logs the connection name, the exception class and its code (e.g. the SQLSTATE) and nothing else:
 * PDO messages carry host, port, database and user names. The response stays generic (framework view / JSON
 * `{"status":"down"}`), with no message, SQL or trace.
 */
final readonly class DatabaseHealthCheck
{
    public function __construct(
        private DatabaseManager $db,
        private LoggerInterface $log,
    ) {}

    public function handle(DiagnosingHealth $event): void
    {
        $connection = $this->db->connection();
        $name = (string) $connection->getName();

        try {
            if ($connection->getDriverName() === 'pgsql') {
                // One fresh connection attempt, without the framework's lost-connection retries.
                $config = $connection->getConfig();
                (new SingleAttemptPostgresConnector)->connect(is_array($config) ? $config : [])->query('select 1');
            } else {
                $connection->select('select 1');
            }
        } catch (Throwable $e) {
            $this->log->error('Health check failed: the database did not answer.', [
                'connection' => $name,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);

            throw new HealthCheckFailed('Health check failed.');
        }
    }
}
