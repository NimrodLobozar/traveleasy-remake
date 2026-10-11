import type { OxlintConfig } from 'vite-plus/lint';

/**
 * Lint configuration for `vp lint` / `vp check` (oxlint + tsgolint), imported by vite.config.ts.
 *
 * TE-003 AC3 asks for the rule sets of ESLint's typescript-eslint `strict-type-checked`,
 * eslint-plugin-react `recommended` + `jsx-runtime`, eslint-plugin-react-hooks `recommended`
 * and eslint-plugin-jsx-a11y `recommended`. Oxlint implements those rules natively, so every
 * rule is listed here explicitly under the oxlint name (`typescript/…`, `react/…`,
 * `jsx-a11y/…`). Oxlint refuses to start when a configured rule doesn't exist, so this list
 * doubles as the proof that each rule is available and on.
 *
 * Known gaps versus the ESLint presets (oxlint has no implementation yet):
 * - typescript/no-generated-empty-object-type and typescript/no-unsafe-enum-assignment;
 *   enums are rare here and tsc's `strict` covers most unsafe enum use.
 * - react/no-deprecated, react/prop-types (prop-types is redundant with TypeScript and is
 *   switched off by typescript-eslint's own React guidance), react/jsx-uses-vars (oxlint's
 *   no-unused-vars understands JSX), react-hooks/config and react-hooks/gating (React
 *   Compiler configuration checks).
 *
 * Formatting rules are never enabled here: formatting belongs to `vp fmt` (oxfmt), so the
 * linter and the formatter can't fight (the role eslint-config-prettier plays for ESLint).
 *
 * Room for later: the SSR-safety rule (no window/document/localStorage at module level or in
 * render) is added by the SSR epic, e.g. as an `overrides` entry for resources/js.
 */

/** typescript-eslint `eslint-recommended` + `@eslint/js` recommended, minus what tsc checks. */
const coreRules = {
    'for-direction': 'error',
    'no-async-promise-executor': 'error',
    'no-case-declarations': 'error',
    'no-compare-neg-zero': 'error',
    'no-cond-assign': 'error',
    'no-constant-binary-expression': 'error',
    'no-constant-condition': 'error',
    'no-control-regex': 'error',
    'no-debugger': 'error',
    'no-delete-var': 'error',
    'no-dupe-else-if': 'error',
    'no-duplicate-case': 'error',
    'no-empty': 'error',
    'no-empty-character-class': 'error',
    'no-empty-pattern': 'error',
    'no-empty-static-block': 'error',
    'no-ex-assign': 'error',
    'no-extra-boolean-cast': 'error',
    'no-fallthrough': 'error',
    'no-global-assign': 'error',
    'no-invalid-regexp': 'error',
    'no-irregular-whitespace': 'error',
    'no-loss-of-precision': 'error',
    'no-misleading-character-class': 'error',
    'no-nonoctal-decimal-escape': 'error',
    'no-prototype-builtins': 'error',
    'no-regex-spaces': 'error',
    'no-self-assign': 'error',
    'no-shadow-restricted-names': 'error',
    'no-sparse-arrays': 'error',
    'no-unexpected-multiline': 'error',
    'no-unsafe-finally': 'error',
    'no-unsafe-optional-chaining': 'error',
    'no-unused-labels': 'error',
    'no-unused-private-class-members': 'error',
    'no-useless-backreference': 'error',
    'no-useless-catch': 'error',
    'no-useless-escape': 'error',
    'no-with': 'error',
    'require-yield': 'error',
    'use-isnan': 'error',
    'valid-typeof': 'error',
    // Added by typescript-eslint's eslint-recommended override.
    'no-var': 'error',
    'prefer-const': 'error',
    'prefer-rest-params': 'error',
    'prefer-spread': 'error',
    // Project rule (TE-003 AC3): no console output at all, not even warn/error.
    'no-console': 'error',
} as const;

/** typescript-eslint 8.71 `strict-type-checked` (core rules it replaces are listed here too). */
const typescriptRules = {
    'typescript/await-thenable': 'error',
    'typescript/ban-ts-comment': ['error', { minimumDescriptionLength: 10 }],
    'no-array-constructor': 'error',
    'typescript/no-array-delete': 'error',
    'typescript/no-base-to-string': 'error',
    'typescript/no-confusing-void-expression': 'error',
    'typescript/no-deprecated': 'error',
    'typescript/no-duplicate-enum-values': 'error',
    'typescript/no-duplicate-type-constituents': 'error',
    'typescript/no-dynamic-delete': 'error',
    'typescript/no-empty-object-type': 'error',
    'typescript/no-explicit-any': 'error',
    'typescript/no-extra-non-null-assertion': 'error',
    'typescript/no-extraneous-class': 'error',
    'typescript/no-floating-promises': 'error',
    'typescript/no-for-in-array': 'error',
    'typescript/no-implied-eval': 'error',
    'typescript/no-invalid-void-type': 'error',
    'typescript/no-meaningless-void-operator': 'error',
    'typescript/no-misused-new': 'error',
    'typescript/no-misused-promises': 'error',
    'typescript/no-misused-spread': 'error',
    'typescript/no-mixed-enums': 'error',
    'typescript/no-namespace': 'error',
    'typescript/no-non-null-asserted-nullish-coalescing': 'error',
    'typescript/no-non-null-asserted-optional-chain': 'error',
    'typescript/no-non-null-assertion': 'error',
    'typescript/no-redundant-type-constituents': 'error',
    'typescript/no-require-imports': 'error',
    'typescript/no-this-alias': 'error',
    'typescript/no-unnecessary-boolean-literal-compare': 'error',
    'typescript/no-unnecessary-condition': 'error',
    'typescript/no-unnecessary-template-expression': 'error',
    'typescript/no-unnecessary-type-arguments': 'error',
    'typescript/no-unnecessary-type-assertion': 'error',
    'typescript/no-unnecessary-type-constraint': 'error',
    'typescript/no-unnecessary-type-conversion': 'error',
    'typescript/no-unnecessary-type-parameters': 'error',
    'typescript/no-unsafe-argument': 'error',
    'typescript/no-unsafe-assignment': 'error',
    'typescript/no-unsafe-call': 'error',
    'typescript/no-unsafe-declaration-merging': 'error',
    'typescript/no-unsafe-enum-comparison': 'error',
    'typescript/no-unsafe-function-type': 'error',
    'typescript/no-unsafe-member-access': 'error',
    'typescript/no-unsafe-return': 'error',
    'typescript/no-unsafe-unary-minus': 'error',
    'no-unused-expressions': 'error',
    'no-unused-vars': 'error',
    'no-useless-constructor': 'error',
    'typescript/no-useless-default-assignment': 'error',
    'typescript/no-wrapper-object-types': 'error',
    'typescript/only-throw-error': 'error',
    'typescript/prefer-as-const': 'error',
    'typescript/prefer-literal-enum-member': 'error',
    'typescript/prefer-namespace-keyword': 'error',
    'typescript/prefer-promise-reject-errors': 'error',
    'typescript/prefer-reduce-type-parameter': 'error',
    'typescript/prefer-return-this-type': 'error',
    'typescript/related-getter-setter-pairs': 'error',
    'typescript/require-await': 'error',
    'typescript/restrict-plus-operands': [
        'error',
        {
            allowAny: false,
            allowBoolean: false,
            allowNullish: false,
            allowNumberAndString: false,
            allowRegExp: false,
        },
    ],
    'typescript/restrict-template-expressions': [
        'error',
        {
            allowAny: false,
            allowBoolean: false,
            allowNever: false,
            allowNullish: false,
            allowNumber: false,
            allowRegExp: false,
        },
    ],
    'typescript/return-await': ['error', 'error-handling-correctness-only'],
    'typescript/triple-slash-reference': 'error',
    'typescript/unbound-method': 'error',
    'typescript/unified-signatures': 'error',
    'typescript/use-unknown-in-catch-callback-variable': 'error',
} as const;

/** eslint-plugin-react 7.37 `recommended` with `jsx-runtime` (no React import needed). */
const reactRules = {
    'react/display-name': 'error',
    'react/jsx-key': 'error',
    'react/jsx-no-comment-textnodes': 'error',
    'react/jsx-no-duplicate-props': 'error',
    'react/jsx-no-target-blank': 'error',
    'react/jsx-no-undef': 'error',
    'react/no-children-prop': 'error',
    'react/no-danger-with-children': 'error',
    'react/no-direct-mutation-state': 'error',
    'react/no-find-dom-node': 'error',
    'react/no-is-mounted': 'error',
    'react/no-render-return-value': 'error',
    'react/no-string-refs': 'error',
    'react/no-unescaped-entities': 'error',
    'react/no-unknown-property': 'error',
    'react/require-render-return': 'error',
    'react/react-in-jsx-scope': 'off',
} as const;

/**
 * eslint-plugin-react-hooks 7.1 `recommended`: the classic hook rules plus the React Compiler
 * rules (oxlint ships them in its react plugin). exhaustive-deps and incompatible-library are
 * warnings in the preset; here they are errors, because `denyWarnings` fails on warnings anyway.
 */
const reactHooksRules = {
    'react/rules-of-hooks': 'error',
    'react/exhaustive-deps': 'error',
    'react/error-boundaries': 'error',
    'react/globals': 'error',
    'react/immutability': 'error',
    'react/incompatible-library': 'error',
    'react/preserve-manual-memoization': 'error',
    'react/purity': 'error',
    'react/refs': 'error',
    'react/set-state-in-effect': 'error',
    'react/set-state-in-render': 'error',
    'react/static-components': 'error',
    'react/unsupported-syntax': 'error',
    'react/use-memo': 'error',
} as const;

/** eslint-plugin-jsx-a11y 6.10 `recommended`, with the preset's options. */
const jsxA11yRules = {
    'jsx-a11y/alt-text': 'error',
    'jsx-a11y/anchor-has-content': 'error',
    'jsx-a11y/anchor-is-valid': 'error',
    'jsx-a11y/aria-activedescendant-has-tabindex': 'error',
    'jsx-a11y/aria-props': 'error',
    'jsx-a11y/aria-proptypes': 'error',
    'jsx-a11y/aria-role': 'error',
    'jsx-a11y/aria-unsupported-elements': 'error',
    'jsx-a11y/autocomplete-valid': 'error',
    'jsx-a11y/click-events-have-key-events': 'error',
    'jsx-a11y/heading-has-content': 'error',
    'jsx-a11y/html-has-lang': 'error',
    'jsx-a11y/iframe-has-title': 'error',
    'jsx-a11y/img-redundant-alt': 'error',
    'jsx-a11y/interactive-supports-focus': [
        'error',
        {
            tabbable: [
                'button',
                'checkbox',
                'link',
                'searchbox',
                'spinbutton',
                'switch',
                'textbox',
            ],
        },
    ],
    'jsx-a11y/label-has-associated-control': 'error',
    'jsx-a11y/media-has-caption': 'error',
    'jsx-a11y/mouse-events-have-key-events': 'error',
    'jsx-a11y/no-access-key': 'error',
    'jsx-a11y/no-autofocus': 'error',
    'jsx-a11y/no-distracting-elements': 'error',
    'jsx-a11y/no-interactive-element-to-noninteractive-role': [
        'error',
        { tr: ['none', 'presentation'], canvas: ['img'] },
    ],
    'jsx-a11y/no-noninteractive-element-interactions': [
        'error',
        {
            handlers: [
                'onClick',
                'onError',
                'onLoad',
                'onMouseDown',
                'onMouseUp',
                'onKeyPress',
                'onKeyDown',
                'onKeyUp',
            ],
            alert: ['onKeyUp', 'onKeyDown', 'onKeyPress'],
            body: ['onError', 'onLoad'],
            dialog: ['onKeyUp', 'onKeyDown', 'onKeyPress'],
            iframe: ['onError', 'onLoad'],
            img: ['onError', 'onLoad'],
        },
    ],
    'jsx-a11y/no-noninteractive-element-to-interactive-role': [
        'error',
        {
            ul: [
                'listbox',
                'menu',
                'menubar',
                'radiogroup',
                'tablist',
                'tree',
                'treegrid',
            ],
            ol: [
                'listbox',
                'menu',
                'menubar',
                'radiogroup',
                'tablist',
                'tree',
                'treegrid',
            ],
            li: [
                'menuitem',
                'menuitemradio',
                'menuitemcheckbox',
                'option',
                'row',
                'tab',
                'treeitem',
            ],
            table: ['grid'],
            td: ['gridcell'],
            fieldset: ['radiogroup', 'presentation'],
        },
    ],
    'jsx-a11y/no-noninteractive-tabindex': [
        'error',
        { tags: [], roles: ['tabpanel'], allowExpressionValues: true },
    ],
    'jsx-a11y/no-redundant-roles': 'error',
    'jsx-a11y/no-static-element-interactions': [
        'error',
        {
            allowExpressionValues: true,
            handlers: [
                'onClick',
                'onMouseDown',
                'onMouseUp',
                'onKeyPress',
                'onKeyDown',
                'onKeyUp',
            ],
        },
    ],
    'jsx-a11y/role-has-required-aria-props': 'error',
    'jsx-a11y/role-supports-aria-props': 'error',
    'jsx-a11y/scope': 'error',
    'jsx-a11y/tabindex-no-positive': 'error',
} as const;

export const lintConfig: OxlintConfig = {
    plugins: ['eslint', 'typescript', 'react', 'jsx-a11y', 'oxc', 'unicorn'],
    categories: {
        correctness: 'error',
    },
    env: {
        browser: true,
        es2024: true,
    },
    rules: {
        ...coreRules,
        ...typescriptRules,
        ...reactRules,
        ...reactHooksRules,
        ...jsxA11yRules,
    },
    // Generated code (Wayfinder, typescript-transformer) and build output; nothing hand-written.
    ignorePatterns: [
        'vendor/**',
        'node_modules/**',
        'public/**',
        'bootstrap/ssr/**',
        'storage/**',
        'resources/js/actions/**',
        'resources/js/routes/**',
        'resources/js/wayfinder/**',
        'resources/js/types/generated/**',
    ],
    options: {
        // Warnings fail the run too, so nothing can be left as "just a warning".
        denyWarnings: true,
        // Type-aware rules (typescript/no-unsafe-*, no-floating-promises, …) run through tsgolint.
        typeAware: true,
    },
};
