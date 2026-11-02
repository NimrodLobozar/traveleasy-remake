<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * The framework's PostgreSQL connector, minus its retry on a "lost connection" (TE-012 AC5).
 *
 * Laravel retries a failed connect when the error looks like a lost connection (refused, unknown host, ...), and the
 * query layer retries once more, so a probe against a database that went away can take four connection attempts.
 * The health check needs one quick answer, so `/up` fails within the container probe's timeout.
 * Not final: tests/Feature/Health/HealthCheckTest.php counts its connection attempts through a subclass.
 */
class SingleAttemptPostgresConnector extends PostgresConnector
{
    /**
     * @param  string  $dsn
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $options
     */
    public function createConnection($dsn, array $config, array $options): PDO
    {
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        return $this->createPdoConnection(
            $dsn,
            is_string($username) ? $username : '',
            is_string($password) ? $password : '',
            $options,
        );
    }
}
