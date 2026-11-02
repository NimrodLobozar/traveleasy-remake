<?php

// Container health check (TE-012 AC5): GET http://127.0.0.1:8080/up with the Host header of APP_URL, so
// trustHosts() does not reject it. Healthy only on HTTP 200. No curl in the image, hence PHP.

declare(strict_types=1);

$host = parse_url((string) getenv('APP_URL'), PHP_URL_HOST);
$host = is_string($host) && $host !== '' ? $host : 'localhost';

$context = stream_context_create(['http' => [
    'method' => 'GET',
    'timeout' => 4,
    'ignore_errors' => true,
    'header' => "Host: {$host}\r\nConnection: close\r\n",
]]);

@file_get_contents('http://127.0.0.1:8080/up', false, $context);

$status = $http_response_header[0] ?? '';
exit(preg_match('#^HTTP/\S+ 200\b#', $status) === 1 ? 0 : 1);
