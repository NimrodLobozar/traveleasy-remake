<?php

declare(strict_types=1);

use Tests\Support\ArchRules;
use Tests\Support\SourceTree;

/*
| TE-004 AC8 + AC13: the app sends no e-mail.
| Source: docs/adr/0005-no-outgoing-email.md (Enforcement: "A Pest arch test forbids the Mail facade, Mailable
| classes, MailMessage and the mail notification channel").
| The whole Mail facade is forbidden (Mail::build/raw/send/to/mailer), as are Illuminate\Mail\*,
| Illuminate\Contracts\Mail\*, Symfony\Component\Mailer\*, the global `Mail` alias, real-time facades of these,
| the 'mail' channel (via(), Notification::route('mail', ...)), toMail(), PHP's mail(), and resolving the mailer
| from the container. The runtime pin (AppServiceProvider + config/mail.php, TE-001 AC10) stays as the second
| line of defence; tests/Feature/ScaffoldContractTest.php and ConfigDefaultsTest.php prove that
| config('mail.default') === 'array' even with MAIL_MAILER=smtp in the environment.
*/

foreach (ArchRules::ARCH_NAMESPACES as $namespace) {
    arch("AC8/AC13 no mail classes or mail functions in {$namespace} (arch)")
        ->expect($namespace)
        ->not->toUse([...ArchRules::MAIL_CLASSES, ...ArchRules::MAIL_FUNCTIONS]);

    arch("AC8 no class in {$namespace} declares toMail() (arch)")
        ->expect($namespace)
        ->not->toHaveMethod('toMail');
}

test('AC8/AC13 no mail in app, bootstrap, config, database and routes (file scan)', function () {
    $violations = ArchRules::check(ArchRules::mail(...), SourceTree::files(ArchRules::PHP_ROOTS));

    expect($violations)->toBe([], ArchRules::report('AC8/AC13 no outgoing e-mail (ADR-0005)', $violations));
});
