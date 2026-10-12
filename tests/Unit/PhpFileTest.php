<?php

declare(strict_types=1);

use Tests\Support\PhpFile;
use Tests\Support\SourceTree;

/*
| TE-004 AC3 (rework round 3): Tests\Support\PhpFile is the only sanctioned way for a test to load a PHP data file
| by a computed path. It refuses everything outside config/ and storage/framework/testing/ at runtime, so it can
| never load bootstrap/app.php (which would boot an app without the TE-002 database guard).
| The paths below are built in variables on purpose: the file-scan rule (ArchRules::guardBypasses) already rejects
| a bootstrap/app.php path written inside the returnValue() call.
*/

test('PhpFile refuses bootstrap/app.php', function (string $relative) {
    $path = SourceTree::root().'/'.$relative;

    expect(fn () => PhpFile::returnValue($path))->toThrow(InvalidArgumentException::class, 'PhpFile refuses');
})->with([
    'direct' => ['bootstrap/app.php'],
    'through config/..' => ['config/../bootstrap/app.php'],
    'through storage/framework/testing/../../..' => ['storage/framework/testing/../../../bootstrap/app.php'],
]);

test('PhpFile refuses other files outside config/ and storage/framework/testing/', function (string $relative) {
    $path = SourceTree::root().'/'.$relative;

    expect(fn () => PhpFile::returnValue($path))->toThrow(InvalidArgumentException::class, 'PhpFile refuses');
})->with([
    'a route file' => ['routes/web.php'],
    'the artisan entry point' => ['artisan'],
    'public/index.php' => ['public/index.php'],
    'a missing config file' => ['config/te004-does-not-exist.php'],
    'the config directory itself' => ['config'],
]);

test('PhpFile refuses a symlink in an allowed directory that points to bootstrap/app.php', function () {
    $link = SourceTree::root().'/storage/framework/testing/te004-phpfile-link-'.getmypid().'.php';
    $target = SourceTree::root().'/bootstrap/app.php';

    try {
        expect(symlink($target, $link))->toBeTrue()
            ->and(fn () => PhpFile::returnValue($link))->toThrow(InvalidArgumentException::class, 'PhpFile refuses');
    } finally {
        @unlink($link);
    }
});

test('PhpFile loads a config file and a generated file under storage/framework/testing/', function () {
    $generated = SourceTree::root().'/storage/framework/testing/te004-phpfile-'.getmypid().'.php';
    file_put_contents($generated, "<?php\n\nreturn ['ok' => true];\n");

    try {
        expect(asArray(PhpFile::returnValue(SourceTree::root().'/config/mail.php')))->toHaveKey('default')
            ->and(PhpFile::returnValue($generated))->toBe(['ok' => true]);
    } finally {
        @unlink($generated);
    }
});
