// Oxlint JS plugin for the SSR-safety rule (TE-007 AC5). Wired up in lint.rules.ts; proven by
// lint-plugins/ssr-safe.test.ts. Plain JavaScript on purpose: oxlint loads it with Node directly.

/** Browser-only globals that don't exist while the page is rendered on the server. */
const BROWSER_GLOBALS = new Set([
    'window',
    'document',
    'localStorage',
    'sessionStorage',
    'navigator',
]);

/** Hooks whose callback argument runs during render (not later, like effects do). */
const RENDER_TIME_CALLBACK_HOOKS = new Set([
    'useState',
    'useMemo',
    'useReducer',
]);

const FUNCTION_TYPES = new Set([
    'FunctionDeclaration',
    'FunctionExpression',
    'ArrowFunctionExpression',
]);

/** @param {string | undefined} name */
function isComponentOrHookName(name) {
    return name !== undefined && /^(?:[A-Z]|use[A-Z0-9])/.test(name);
}

/**
 * Name of a function, from its declaration or the variable it is assigned to, looking through
 * wrappers such as `memo(...)` and `forwardRef(...)`.
 * @param {any} fn
 * @returns {string | undefined}
 */
function functionName(fn) {
    if (fn.id?.name) {
        return fn.id.name;
    }
    let node = fn;
    while (
        node.parent?.type === 'CallExpression' &&
        node.parent.arguments.includes(node)
    ) {
        node = node.parent;
    }
    const parent = node.parent;
    if (
        parent?.type === 'VariableDeclarator' &&
        parent.id.type === 'Identifier'
    ) {
        return parent.id.name;
    }
    return undefined;
}

/** True when the function is the callback of useState/useMemo/useReducer. */
function isRenderTimeCallback(fn) {
    const call = fn.parent;
    if (call?.type !== 'CallExpression' || call.arguments[0] !== fn) {
        return false;
    }
    const callee = call.callee;
    const name =
        callee.type === 'Identifier'
            ? callee.name
            : callee.type === 'MemberExpression'
              ? callee.property?.name
              : undefined;

    return name !== undefined && RENDER_TIME_CALLBACK_HOOKS.has(name);
}

/**
 * Is this identifier an actual reference to the global (not a property name, a type, a
 * `typeof window` guard or a local variable with the same name)?
 */
function isUnguardedGlobalReference(node, context) {
    const parent = node.parent;
    if (
        parent?.type === 'MemberExpression' &&
        parent.property === node &&
        !parent.computed
    ) {
        return false;
    }
    if (
        parent?.type === 'Property' &&
        parent.key === node &&
        !parent.computed &&
        !parent.shorthand
    ) {
        return false;
    }
    if (parent?.type === 'UnaryExpression' && parent.operator === 'typeof') {
        return false;
    }
    for (let ancestor = parent; ancestor; ancestor = ancestor.parent) {
        if (ancestor.type.startsWith('TS')) {
            return false;
        }
    }
    let scope = context.sourceCode.getScope(node);
    while (scope) {
        if (
            scope.variables.some(
                (variable) =>
                    variable.name === node.name && variable.defs.length > 0,
            )
        ) {
            return false;
        }
        scope = scope.upper;
    }

    return true;
}

/**
 * Where does the code run? 'module' = while the module loads, 'render' = while a component or
 * hook body runs, 'later' = inside a nested function (effect, event handler, helper) or
 * somewhere we can't tell, which is allowed.
 */
function whenItRuns(node) {
    let fn = null;
    for (let ancestor = node.parent; ancestor; ancestor = ancestor.parent) {
        if (FUNCTION_TYPES.has(ancestor.type)) {
            fn = ancestor;
            break;
        }
        if (ancestor.type === 'ClassBody') {
            return 'later';
        }
    }
    if (fn === null) {
        return 'module';
    }
    // A useState/useMemo/useReducer callback inside a component runs during render too.
    while (isRenderTimeCallback(fn)) {
        let outer = fn.parent;
        while (outer && !FUNCTION_TYPES.has(outer.type)) {
            outer = outer.parent;
        }
        if (!outer) {
            return 'module';
        }
        fn = outer;
    }

    return isComponentOrHookName(functionName(fn)) ? 'render' : 'later';
}

/** @type {import('@oxlint/plugins').Rule} */
const noBrowserGlobalsInRender = {
    meta: {
        type: 'problem',
        docs: {
            description:
                'Disallow window, document, localStorage, sessionStorage and navigator at module level or during render (breaks server-side rendering).',
        },
        messages: {
            unsafe: '`{{name}}` does not exist while rendering on the server ({{where}}). Move it into useEffect or an event handler.',
        },
        schema: [],
    },
    create(context) {
        return {
            Identifier(node) {
                if (!BROWSER_GLOBALS.has(node.name)) {
                    return;
                }
                if (!isUnguardedGlobalReference(node, context)) {
                    return;
                }
                const when = whenItRuns(node);
                if (when === 'later') {
                    return;
                }
                context.report({
                    node,
                    messageId: 'unsafe',
                    data: {
                        name: node.name,
                        where:
                            when === 'module'
                                ? 'at module level'
                                : 'during render',
                    },
                });
            },
        };
    },
};

/**
 * Components are bundled for the browser and for the SSR server, and the SSR process holds the
 * server's environment. `process.env.X` in a component would render a server variable into the
 * HTML (TE-008 AC10), so `process` is reserved for the server entry (ssr.tsx); lint.rules.ts
 * turns this rule off for that file and for tests. Use `import.meta.env.VITE_*` instead.
 * @type {import('@oxlint/plugins').Rule}
 */
const noNodeProcess = {
    meta: {
        type: 'problem',
        docs: {
            description:
                'Disallow the Node `process` global (and node:process) outside the SSR entry, so components cannot read server environment variables.',
        },
        messages: {
            forbidden:
                '`process` is only for the SSR entry (ssr.tsx). A component could render a server environment variable into the HTML; use import.meta.env.VITE_* for public settings.',
        },
        schema: [],
    },
    create(context) {
        return {
            Identifier(node) {
                if (
                    node.name === 'process' &&
                    isUnguardedGlobalReference(node, context)
                ) {
                    context.report({ node, messageId: 'forbidden' });
                }
            },
            ImportDeclaration(node) {
                if (
                    node.source.value === 'node:process' ||
                    node.source.value === 'process'
                ) {
                    context.report({ node, messageId: 'forbidden' });
                }
            },
        };
    },
};

export default {
    meta: { name: 'ssr-safe' },
    rules: {
        'no-browser-globals-in-render': noBrowserGlobalsInRender,
        'no-node-process': noNodeProcess,
    },
};
