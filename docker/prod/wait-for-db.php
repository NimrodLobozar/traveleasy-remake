<?php

// Waits until PostgreSQL accepts a login (TE-012 AC6). Reads DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME,
// DB_PASSWORD and DB_WAIT_TIMEOUT (seconds, default 60). Prints only host, port and PDO's reason, never the
// password. Exits 1 on timeout.

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';
$timeout = max(1, (int) (getenv('DB_WAIT_TIMEOUT') ?: 60));

$deadline = time() + $timeout;
$reason = '';

while (true) {
    try {
        new PDO("pgsql:host={$host};port={$port};dbname={$name};connect_timeout=3", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        fwrite(STDERR, "[entrypoint] database {$host}:{$port} is ready\n");
        exit(0);
    } catch (PDOException $e) {
        $reason = strtok($e->getMessage(), "\n") ?: 'unknown error';
    }

    if (time() >= $deadline) {
        fwrite(STDERR, "[entrypoint] ERROR: database {$host}:{$port} not reachable after {$timeout} s ({$reason}). "
            ."Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD and that the db service is healthy.\n");
        exit(1);
    }

    sleep(2);
}
