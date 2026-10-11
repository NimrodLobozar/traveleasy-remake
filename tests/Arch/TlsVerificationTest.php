<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC6: TLS verification is never turned off.
| Source: docs/legacy-analysis.md § 3 Security ("the HTTP client used withoutVerifying()").
| Catches withoutVerifying() (also as a string callable), 'verify' / 'verify_peer' / 'verify_peer_name' set to
| anything but true (or a CA-bundle path for 'verify'), RequestOptions::VERIFY => false, 'allow_self_signed'
| other than false, and the cURL CURLOPT_SSL_VERIFY* options.
*/

test('AC6 TLS verification stays on in app, bootstrap, config, database and routes (file scan)', function () {
    $violations = ArchRules::check(ArchRules::tlsVerificationDisabled(...), SourceTree::files(ArchRules::PHP_ROOTS));

    expect($violations)->toBe([], ArchRules::report('AC6 TLS verification stays on (legacy § 3)', $violations));
});
