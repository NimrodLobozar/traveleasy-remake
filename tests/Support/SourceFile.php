<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpToken;

/**
 * One PHP (or compiled Blade) source file, tokenised for the file-scan rules in tests/Arch (TE-004).
 *
 * Pest's arch expectations only see namespaced classes, so files without a namespace (config/, routes/,
 * bootstrap/, migrations, views) are checked with this scanner. It works on PHP tokens, not on text, so:
 *  - comments never trigger a rule, and a rule can't be hidden by spacing or line breaks;
 *  - class names are resolved the way PHP resolves them: `use ... as Alias`, grouped `use A\{B, C}`,
 *    fully qualified `\A\B`, `namespace\B`, real-time facades (`Facades\A\B`) and class names written as
 *    string literals ('A\B') all end up as the same fully qualified name, compared case-insensitively;
 *  - function calls are resolved through `use function ... as ...` and a leading backslash.
 *
 * Known limits (deliberate evasion, not accidental use): names built at runtime (string concatenation,
 * variables, `call_user_func($name)`) are not resolved.
 */
final class SourceFile
{
    public const KIND_IMPORT = 'import';

    public const KIND_EXTENDS = 'extends';

    public const KIND_CODE = 'code';

    public const KIND_STRING = 'string';

    private const NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    private const IGNORED_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG];

    private const DECLARATION_TOKENS = [T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_GOTO];

    /** @var list<PhpToken> Code tokens, without whitespace and comments. */
    public readonly array $tokens;

    /** @var list<array{name: string, line: int, kind: string, index: int}> Fully qualified class names this file refers to. */
    public private(set) array $classReferences = [];

    /** @var list<array{name: string, line: int, index: int}> Lower-case, resolved names of called functions. */
    public private(set) array $functionCalls = [];

    /** @var list<array{name: string, line: int, index: int}> Lower-case names of called methods (->x(), ?->x(), ::x()). */
    public private(set) array $methodCalls = [];

    /** @var list<array{name: string, line: int, index: int}> Lower-case names of declared functions and methods. */
    public private(set) array $functionDeclarations = [];

    /** @var list<array{value: string, line: int, index: int}> Unquoted string literals. */
    public private(set) array $strings = [];

    /** @var list<array{name: string, line: int}> Bare identifiers (last segment), e.g. constants. */
    public private(set) array $identifiers = [];

    /** @var list<array{name: string, line: int}> Variables, with the `$`. */
    public private(set) array $variables = [];

    /** @var list<int> Lines with exit/die. */
    public private(set) array $exits = [];

    /** @var list<array{name: string, line: int}> echo / print statements (lower-case keyword). */
    public private(set) array $outputs = [];

    /** @var list<string> Fully qualified names of the classes, interfaces, traits and enums declared here. */
    public private(set) array $declaredClasses = [];

    /** @var array<string, string> Declared class => the fully qualified class it extends. */
    public private(set) array $classParents = [];

    private string $namespace = '';

    /** @var array<string, string> lower-case alias => fully qualified class name */
    private array $classImports = [];

    /** @var array<string, string> lower-case alias => lower-case fully qualified function name */
    private array $functionImports = [];

    /**
     * @param  string  $path  Path relative to the project root, with forward slashes.
     */
    public function __construct(public readonly string $path, string $code)
    {
        $this->tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn (PhpToken $token): bool => ! $token->is(self::IGNORED_TOKENS),
        ));

        $this->analyse();
    }

    public function token(int $index): ?PhpToken
    {
        return $this->tokens[$index] ?? null;
    }

    /** Index of the bracket that closes the one at $open ( `(`, `[` or `{` ), or the last index. */
    public function closingIndex(int $open): int
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $depth = 0;

        for ($i = $open, $count = count($this->tokens); $i < $count; $i++) {
            $text = $this->tokens[$i]->text;

            if (isset($pairs[$text]) || $this->tokens[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif (in_array($text, $pairs, true)) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return count($this->tokens) - 1;
    }

    /** Concatenated token texts between two indexes (exclusive), without whitespace. */
    public function textBetween(int $from, int $to): string
    {
        $text = '';

        for ($i = $from + 1; $i < $to; $i++) {
            $text .= $this->tokens[$i]->text;
        }

        return $text;
    }

    /**
     * The value of a quoted string literal, unescaped exactly as PHP does. Other tokens are returned as they are.
     */
    public static function unquote(PhpToken $token): string
    {
        $text = $token->text;

        if (! $token->is(T_CONSTANT_ENCAPSED_STRING)) {
            return $text;
        }

        $body = substr($text, 1, -1);

        return $text[0] === "'" ? self::unescapeSingleQuoted($body) : self::unescapeDoubleQuoted($body, true);
    }

    /** Single quotes: only `\\` and `\'` are escapes; every other backslash stays. */
    public static function unescapeSingleQuoted(string $body): string
    {
        return (string) preg_replace('/\\\\([\\\\\'])/', '$1', $body);
    }

    /**
     * Double quotes and heredoc: only \n \t \r \v \e \f \\ \$ \0-7{1,3} \xHH \u{...} are escapes (plus \" in double
     * quotes, not in heredoc). Every other backslash stays, so "Illuminate\Mail\Mailer" keeps its backslashes.
     */
    public static function unescapeDoubleQuoted(string $body, bool $quoteIsEscape): string
    {
        $simple = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$'];

        return (string) preg_replace_callback(
            '/\\\\(?:([ntrvef\\\\$])|(")|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/',
            static function (array $match) use ($simple, $quoteIsEscape): string {
                $escape = substr($match[0], 1);

                return match (true) {
                    isset($simple[$escape]) => $simple[$escape],
                    $escape === '"' => $quoteIsEscape ? '"' : '\\"',
                    $escape[0] === 'x' => chr((int) hexdec(substr($escape, 1)) & 0xFF),
                    $escape[0] === 'u' => mb_chr((int) hexdec(substr($escape, 2, -1)), 'UTF-8') ?: '',
                    default => chr((int) octdec($escape) & 0xFF),
                };
            },
            $body,
        );
    }

    /** Strips a leading backslash and a real-time facade prefix (`Facades\`). */
    public static function normaliseClass(string $name): string
    {
        $name = ltrim($name, '\\');

        return stripos($name, 'Facades\\') === 0 ? substr($name, strlen('Facades\\')) : $name;
    }

    /** True when $name is $target itself or a class inside the namespace $target (case-insensitive). */
    public static function isWithin(string $name, string $target): bool
    {
        $name = strtolower($name);
        $target = strtolower(trim($target, '\\'));

        return $name === $target || str_starts_with($name, $target.'\\');
    }

    private function analyse(): void
    {
        $depth = 0;
        $inDoubleQuotes = false;
        /** @var array{nowdoc: bool, indent: string}|null $heredoc */
        $heredoc = null;

        for ($i = 0, $count = count($this->tokens); $i < $count; $i++) {
            $token = $this->tokens[$i];

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;

                continue;
            }

            if ($token->text === '}') {
                $depth--;

                continue;
            }

            if ($token->is(T_NAMESPACE)) {
                $next = $this->token($i + 1);
                $this->namespace = $next !== null && $next->is([T_STRING, T_NAME_QUALIFIED]) ? $next->text : '';
                $this->classImports = [];
                $this->functionImports = [];

                continue;
            }

            if ($token->is(T_USE) && $depth === 0 && $this->token($i + 1)?->text !== '(') {
                $i = $this->parseImport($i);

                continue;
            }

            if ($token->is(T_EXIT)) {
                $this->exits[] = $token->line;
            } elseif ($token->is([T_ECHO, T_PRINT])) {
                $this->outputs[] = ['name' => strtolower($token->text), 'line' => $token->line];
            } elseif ($token->is(T_VARIABLE)) {
                $this->variables[] = ['name' => $token->text, 'line' => $token->line];
            } elseif ($token->is(T_CONSTANT_ENCAPSED_STRING)) {
                $this->addString($i, $token, self::unquote($token));
            } elseif ($token->is(T_START_HEREDOC)) {
                $heredoc = $this->heredocContext($i);
            } elseif ($token->is(T_END_HEREDOC)) {
                $heredoc = null;
            } elseif ($token->text === '"') {
                $inDoubleQuotes = ! $inDoubleQuotes;
            } elseif ($token->is(T_ENCAPSED_AND_WHITESPACE)) {
                $this->addString($i, $token, $this->encapsedValue($i, $heredoc, $inDoubleQuotes));
            } elseif ($token->is(self::NAME_TOKENS)) {
                $this->addName($i, $token);
            }
        }
    }

    /**
     * Whether the heredoc starting at $start is a nowdoc, and the indentation of its closing marker (PHP removes
     * that indentation from every body line).
     *
     * @return array{nowdoc: bool, indent: string}
     */
    private function heredocContext(int $start): array
    {
        $nowdoc = str_contains($this->tokens[$start]->text, "'");
        $indent = '';

        for ($i = $start + 1, $count = count($this->tokens); $i < $count; $i++) {
            if ($this->tokens[$i]->is(T_END_HEREDOC)) {
                preg_match('/^[ \t]*/', $this->tokens[$i]->text, $match);
                $indent = $match[0] ?? '';

                break;
            }
        }

        return ['nowdoc' => $nowdoc, 'indent' => $indent];
    }

    /**
     * The value of a T_ENCAPSED_AND_WHITESPACE part: a piece of an interpolated double-quoted string, or a
     * heredoc/nowdoc body (closing indentation removed, the newline before the closing marker trimmed).
     *
     * @param  array{nowdoc: bool, indent: string}|null  $heredoc
     */
    private function encapsedValue(int $i, ?array $heredoc, bool $inDoubleQuotes): string
    {
        $text = $this->tokens[$i]->text;

        if ($heredoc === null) {
            return $inDoubleQuotes ? self::unescapeDoubleQuoted($text, true) : $text;
        }

        if ($heredoc['indent'] !== '') {
            $lineStart = $this->token($i - 1)?->is(T_START_HEREDOC) === true ? '(^|\n)' : '(\n)';
            $text = (string) preg_replace('/'.$lineStart.preg_quote($heredoc['indent'], '/').'/', '$1', $text);
        }

        if ($this->token($i + 1)?->is(T_END_HEREDOC) === true) {
            $text = (string) preg_replace('/\r?\n$/', '', $text);
        }

        return $heredoc['nowdoc'] ? $text : self::unescapeDoubleQuoted($text, false);
    }

    /** Parses a top-level `use` statement and returns the index of its closing `;`. */
    private function parseImport(int $use): int
    {
        $line = $this->tokens[$use]->line;
        $i = $use + 1;
        $kind = $this->importKind($i);

        while (($name = $this->token($i)) !== null && $name->is(self::NAME_TOKENS)) {
            $i++;

            if ($this->token($i)?->is(T_NS_SEPARATOR) === true && $this->token($i + 1)?->text === '{') {
                // Grouped import: use Prefix\{A, B as C, function d};
                $prefix = ltrim($name->text, '\\');
                $i += 2;

                while (($item = $this->token($i)) !== null && $item->text !== '}') {
                    if ($item->text === ',') {
                        $i++;

                        continue;
                    }

                    $itemKind = $this->importKind($i) ?? $kind;
                    $member = $this->token($i);

                    if ($member === null || ! $member->is(self::NAME_TOKENS)) {
                        break;
                    }

                    $i++;
                    $alias = $this->importAlias($i, $member->text);
                    $this->registerImport($itemKind, $prefix.'\\'.ltrim($member->text, '\\'), $alias, $line, $use);
                }

                $i++;
            } else {
                $alias = $this->importAlias($i, $name->text);
                $this->registerImport($kind, ltrim($name->text, '\\'), $alias, $line, $use);
            }

            if ($this->token($i)?->text !== ',') {
                break;
            }

            $i++;
        }

        return $i;
    }

    /** Consumes an optional `function` / `const` keyword at $i. */
    private function importKind(int &$i): ?string
    {
        $token = $this->token($i);

        if ($token?->is(T_FUNCTION) === true || $token?->is(T_CONST) === true) {
            $i++;

            return strtolower($token->text);
        }

        return null;
    }

    /** Consumes an optional `as Alias` at $i and returns the alias (the last name segment otherwise). */
    private function importAlias(int &$i, string $name): string
    {
        if ($this->token($i)?->is(T_AS) === true) {
            $aliasToken = $this->token($i + 1);
            $alias = $aliasToken === null ? $name : $aliasToken->text;
            $i += 2;

            return $alias;
        }

        $segments = explode('\\', $name);

        return end($segments);
    }

    private function registerImport(?string $kind, string $name, string $alias, int $line, int $index): void
    {
        if ($kind === 'function') {
            $this->functionImports[strtolower($alias)] = strtolower($name);
        } elseif ($kind === null) {
            $this->classImports[strtolower($alias)] = $name;
            $this->addClassReference($name, $line, self::KIND_IMPORT, $index);
        }
    }

    private function addString(int $i, PhpToken $token, string $value): void
    {
        $this->strings[] = ['value' => $value, 'line' => $token->line, 'index' => $i];

        if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $value) === 1) {
            $this->addClassReference($value, $token->line, self::KIND_STRING, $i);
        }
    }

    private function addName(int $i, PhpToken $token): void
    {
        $previous = $this->token($i - 1);
        $next = $this->token($i + 1);
        $segments = explode('\\', $token->text);
        $this->identifiers[] = ['name' => end($segments), 'line' => $token->line];

        if ($previous !== null && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])) {
            if ($next?->text === '(' && $token->is(T_STRING)) {
                $this->methodCalls[] = ['name' => strtolower($token->text), 'line' => $token->line, 'index' => $i];
            }

            return;
        }

        if ($previous !== null && ($previous->is(T_FUNCTION) || ($previous->text === '&' && $this->token($i - 2)?->is(T_FUNCTION) === true))) {
            $this->functionDeclarations[] = ['name' => strtolower($token->text), 'line' => $token->line, 'index' => $i];

            return;
        }

        if ($previous !== null && $previous->is(self::DECLARATION_TOKENS)) {
            if ($previous->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && $this->token($i - 2)?->is(T_NEW) !== true) {
                $this->declaredClasses[] = ltrim($this->namespace.'\\'.$token->text, '\\');
            }

            return;
        }

        if ($previous?->is(T_CASE) === true && $next?->is(T_DOUBLE_COLON) !== true) {
            return; // enum case name
        }

        if ($token->is(T_STRING) && in_array(strtolower($token->text), ['true', 'false', 'null', 'self', 'static', 'parent'], true)) {
            return;
        }

        if ($next?->text === ':' && $token->is(T_STRING) && in_array($previous?->text, ['(', ','], true)) {
            return; // named argument
        }

        if ($next?->text === '(' && $previous?->is(T_NEW) !== true) {
            $this->functionCalls[] = ['name' => $this->resolveFunction($token), 'line' => $token->line, 'index' => $i];

            return;
        }

        $class = $this->resolveClass($token);
        $extends = $previous?->is(T_EXTENDS) === true;

        if ($extends && $this->declaredClasses !== []) {
            $this->classParents[$this->declaredClasses[array_key_last($this->declaredClasses)]] = self::normaliseClass($class);
        }

        $this->addClassReference($class, $token->line, $extends ? self::KIND_EXTENDS : self::KIND_CODE, $i);
    }

    private function addClassReference(string $name, int $line, string $kind, int $index): void
    {
        $this->classReferences[] = ['name' => self::normaliseClass($name), 'line' => $line, 'kind' => $kind, 'index' => $index];
    }

    private function resolveClass(PhpToken $token): string
    {
        if ($token->is(T_NAME_FULLY_QUALIFIED)) {
            return substr($token->text, 1);
        }

        if ($token->is(T_NAME_RELATIVE)) {
            return ltrim($this->namespace.substr($token->text, strlen('namespace')), '\\');
        }

        $parts = explode('\\', $token->text, 2);
        $imported = $this->classImports[strtolower($parts[0])] ?? null;
        $rest = $parts[1] ?? null;

        if ($imported !== null) {
            return $rest === null ? $imported : $imported.'\\'.$rest;
        }

        return ltrim($this->namespace.'\\'.$token->text, '\\');
    }

    private function resolveFunction(PhpToken $token): string
    {
        if ($token->is(T_NAME_FULLY_QUALIFIED)) {
            return strtolower(substr($token->text, 1));
        }

        if ($token->is(T_STRING)) {
            // Unqualified calls fall back to the global function, so treat them as global.
            return $this->functionImports[strtolower($token->text)] ?? strtolower($token->text);
        }

        return strtolower($this->resolveClass($token));
    }
}
