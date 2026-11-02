<?php

declare(strict_types=1);

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * TE-015 AC9: markup rules that keep the CSP effective. Inline event handlers (`onclick="…"`) and `javascript:` URLs
 * are blocked by a nonce-based CSP anyway, so they would only be dead, broken code; raw-HTML sinks
 * (`dangerouslySetInnerHTML`, `innerHTML`, …) are where injected markup gets in.
 *
 * Text scans (Blade and TS/TSX are not PHP, so the PHP tokeniser in SourceFile doesn't apply). Comments are removed
 * first, so a rule mentioned in a comment never triggers.
 */
final class MarkupRules
{
    public const BLADE_ROOT = 'resources/views';

    public const JS_ROOT = 'resources/js';

    /**
     * Reviewed raw-HTML sinks: file => why its input can't carry user content. Every new entry needs a security review.
     */
    public const RAW_HTML_ALLOWED = [
        'resources/js/components/two-factor-setup-modal.tsx' => 'Fortify-generated QR-code SVG from the server, no user input',
    ];

    /** An HTML event-handler attribute: `onclick=`, `onload =`, … (not `x-on:click`, `data-onx`, `:onclick`). */
    private const HANDLER_ATTRIBUTE = '/(?<![\w:@.\-])on[a-z]{3,}\s*=\s*(?:["\'{]|[^\s>])/i';

    private const JAVASCRIPT_URL = '/\bjavascript\s*:/i';

    /** Raw-HTML sinks in TS/TSX. */
    private const RAW_HTML_SINKS = '/\bdangerouslySetInnerHTML\b|\.(?:inner|outer)HTML\s*\+?=|\binsertAdjacentHTML\s*\(|\bdocument\s*\.\s*write(?:ln)?\s*\(|\bsetAttribute\s*\(\s*[\'"`]on/';

    /**
     * Violations in one Blade view.
     *
     * @return list<string> "line: what"
     */
    public static function blade(string $code): array
    {
        $code = self::blank($code, '/\{\{--.*?--\}\}|<!--.*?-->/s');

        return [
            ...self::matches($code, self::HANDLER_ATTRIBUTE, 'inline event handler attribute'),
            ...self::matches($code, self::JAVASCRIPT_URL, 'javascript: URL'),
        ];
    }

    /**
     * Violations in one TS/TSX file.
     *
     * @return list<string> "line: what"
     */
    public static function script(string $code, bool $rawHtmlAllowed = false): array
    {
        // Block comments, then line comments (a `//` after `:` is a URL in a string, not a comment).
        $code = self::blank($code, '#/\*.*?\*/#s');
        $code = self::blank($code, '#(?<![:\\\\])//[^\n]*#');

        $violations = [
            // In JSX, `onClick={fn}` is fine (React attaches a listener); a lowercase string attribute is inline code.
            ...self::matches($code, '/(?<![\w.\-])on[a-z]{3,}\s*=\s*["\']/', 'inline event handler attribute'),
            ...self::matches($code, self::JAVASCRIPT_URL, 'javascript: URL'),
        ];

        if (! $rawHtmlAllowed) {
            $violations = [...$violations, ...self::matches($code, self::RAW_HTML_SINKS, 'raw-HTML sink')];
        }

        return $violations;
    }

    /**
     * Project files below a root with the given suffixes, relative to the project root. Test files are skipped.
     *
     * @param  list<string>  $suffixes
     * @return list<string>
     */
    public static function files(string $root, array $suffixes): array
    {
        $base = SourceTree::root();
        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base.'/'.$root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

            if (preg_match('/\.(test|spec)\.tsx?$/', $relative) === 1 || str_contains($relative, '/test/')) {
                continue;
            }

            foreach ($suffixes as $suffix) {
                if (str_ends_with($relative, $suffix)) {
                    $files[] = $relative;

                    break;
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Replace matches with spaces, keeping newlines, so line numbers stay right.
     */
    private static function blank(string $code, string $pattern): string
    {
        return (string) preg_replace_callback(
            $pattern,
            fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
            $code,
        );
    }

    /**
     * @return list<string>
     */
    private static function matches(string $code, string $pattern, string $what): array
    {
        preg_match_all($pattern, $code, $found, PREG_OFFSET_CAPTURE);
        $violations = [];

        foreach ($found[0] as [$text, $offset]) {
            $line = substr_count(substr($code, 0, $offset), "\n") + 1;
            $violations[] = "{$line}: {$what} (".trim($text).')';
        }

        return $violations;
    }
}
