<?php

declare(strict_types=1);

use App\Support\EmailAddress;

/*
| TE-049: the one shared e-mail normalisation (trim + lowercase).
*/

it('trims surrounding whitespace and lowercases the address', function (string $input, string $expected) {
    expect(EmailAddress::normalise($input))->toBe($expected);
})->with([
    'already normal' => ['jan@example.com', 'jan@example.com'],
    'mixed case' => ['Jan@Example.COM', 'jan@example.com'],
    'surrounding spaces' => ["  Jan@Example.com \t\n", 'jan@example.com'],
    'multibyte' => ['ÉLODIE@Exämple.test', 'élodie@exämple.test'],
    'empty' => ['   ', ''],
]);

it('is idempotent', function () {
    $once = EmailAddress::normalise(' Owner@Example.COM ');

    expect(EmailAddress::normalise($once))->toBe($once);
});
