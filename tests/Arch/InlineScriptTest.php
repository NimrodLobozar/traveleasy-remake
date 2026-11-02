<?php

declare(strict_types=1);

use Tests\Support\MarkupRules;
use Tests\Support\SourceTree;

/*
| TE-015 AC9: no inline event handlers, no javascript: URLs and no unreviewed raw-HTML sinks in Blade views and
| React code, so the nonce-based CSP never needs 'unsafe-inline' and injected markup has no way in.
| Source: docs/plan.md § 4 M0 App shell (CSP with nonces); legacy § 3 Security.
*/

test('AC9 Blade views have no inline event handlers or javascript: URLs', function () {
    $violations = [];

    foreach (MarkupRules::files(MarkupRules::BLADE_ROOT, ['.blade.php']) as $file) {
        foreach (MarkupRules::blade((string) file_get_contents(SourceTree::root().'/'.$file)) as $violation) {
            $violations[] = "{$file}:{$violation}";
        }
    }

    expect($violations)->toBe([], "AC9 inline script in Blade (TE-015):\n".implode("\n", $violations));
});

test('AC9 React code has no inline handlers, javascript: URLs or unreviewed raw-HTML sinks', function () {
    $violations = [];

    foreach (MarkupRules::files(MarkupRules::JS_ROOT, ['.ts', '.tsx']) as $file) {
        $code = (string) file_get_contents(SourceTree::root().'/'.$file);

        foreach (MarkupRules::script($code, array_key_exists($file, MarkupRules::RAW_HTML_ALLOWED)) as $violation) {
            $violations[] = "{$file}:{$violation}";
        }
    }

    expect($violations)->toBe([], "AC9 inline script or raw HTML in React code (TE-015):\n".implode("\n", $violations));
});

test('AC9 every raw-HTML allow-list entry still exists and still needs it', function () {
    foreach (array_keys(MarkupRules::RAW_HTML_ALLOWED) as $file) {
        $code = (string) file_get_contents(SourceTree::root().'/'.$file);

        expect(MarkupRules::script($code))->not->toBe([], "{$file} no longer needs its allow-list entry; remove it");
    }
});

test('AC9 the Blade rule bites', function (string $snippet) {
    expect(MarkupRules::blade($snippet))->not->toBe([]);
})->with([
    'onclick' => ['<button onclick="go()">Go</button>'],
    'onload upper case' => ['<body ONLOAD="init()">'],
    'spaced' => ['<img src="x" onerror = "alert(1)">'],
    'unquoted' => ['<img src=x onerror=alert(1)>'],
    'multi-line tag' => ["<a\n    href=\"/\"\n    onmouseover='x()'>"],
    'javascript url' => ['<a href="javascript:alert(1)">x</a>'],
    'javascript url spaced' => ['<a href="JavaScript :void(0)">x</a>'],
    'after a blade echo' => ['<div class="{{ $c }}" onfocus="x()"></div>'],
]);

test('AC9 the Blade rule ignores clean markup', function (string $snippet) {
    expect(MarkupRules::blade($snippet))->toBe([]);
})->with([
    'plain' => ['<button type="button" class="btn">Go</button>'],
    'data attribute' => ['<div data-online="1" data-on="x"></div>'],
    'word in text' => ['<p>Click on the button. Turn it on = off.</p>'],
    'blade comment' => ['{{-- never use onclick="x()" --}}<p></p>'],
    'html comment' => ['<!-- onclick="x()" --><p></p>'],
    'component binding' => ['<x-button :online="$state" />'],
]);

test('AC9 the React rule bites', function (string $snippet) {
    expect(MarkupRules::script($snippet))->not->toBe([]);
})->with([
    'dangerouslySetInnerHTML' => ['<div dangerouslySetInnerHTML={{ __html: input }} />'],
    'innerHTML assignment' => ['el.innerHTML = value;'],
    'innerHTML append' => ['el.innerHTML += value;'],
    'outerHTML' => ['node.outerHTML = html;'],
    'insertAdjacentHTML' => ["el.insertAdjacentHTML('beforeend', html);"],
    'document.write' => ['document.write(html);'],
    'setAttribute on-handler' => ["el.setAttribute('onclick', code);"],
    'string handler attribute' => ['<button onclick="go()" />'],
    'javascript url' => ['<a href="javascript:void(0)" />'],
]);

test('AC9 the React rule ignores clean code and comments', function (string $snippet) {
    expect(MarkupRules::script($snippet))->toBe([]);
})->with([
    'react handler' => ['<button onClick={() => go()} />'],
    'react handler prop' => ['<Input onChange={handle} onValueChange={set} />'],
    'line comment' => ['// never pass t() to dangerouslySetInnerHTML'],
    'block comment' => ['/** produces HTML, never innerHTML = x */ const a = 1;'],
    'url in string' => ["const u = 'https://example.com/a'; // ok"],
    'textContent' => ['el.textContent = value;'],
]);

test('AC9 an allow-listed file may use a raw-HTML sink, but not inline handlers', function () {
    expect(MarkupRules::script('<div dangerouslySetInnerHTML={{ __html: svg }} />', rawHtmlAllowed: true))->toBe([])
        ->and(MarkupRules::script('<a href="javascript:x()" />', rawHtmlAllowed: true))->not->toBe([]);
});
