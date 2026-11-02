// Fixture test for the SSR-safe lint rule (TE-007 AC5): browser globals fail at module level and
// during render, and pass in effects, event handlers and `typeof` checks.
import { RuleTester } from 'oxlint/plugins-dev';
import { describe, it } from 'vite-plus/test';
import plugin from './ssr-safe.js';

RuleTester.describe = describe;
RuleTester.it = it;
RuleTester.itOnly = it.only;

const rule = plugin.rules['no-browser-globals-in-render'];
const languageOptions = {
    sourceType: 'module',
    parserOptions: { lang: 'tsx' },
} as const;
const tester = new RuleTester({ languageOptions });

const message = /Move it into useEffect or an event handler/;

tester.run('ssr-safe/no-browser-globals-in-render', rule, {
    valid: [
        // Inside an effect.
        `export function Page() {
            useEffect(() => { document.title = 'x'; localStorage.getItem('a'); }, []);
            return null;
        }`,
        // Inside an event handler, inline or declared in render.
        `export function Page() {
            const onPress = () => { navigator.clipboard.writeText('x'); };
            return <button onClick={() => { window.scrollTo(0, 0); }} onKeyDown={onPress}>x</button>;
        }`,
        // Effect callbacks inside a custom hook.
        `export function useThing() {
            useEffect(() => { window.addEventListener('resize', f); return () => { window.removeEventListener('resize', f); }; }, []);
        }`,
        // typeof is safe on the server; plain helpers are only checked where they are called.
        `const isBrowser = typeof window !== 'undefined';
        function helper() { return window.innerWidth; }`,
        // A local variable with the same name is not the global.
        `export function Page({ window }) { return <p>{window.title}</p>; }`,
        // Property names are not references.
        `export function Page({ page }) { return <p>{page.window} {page.document.title}</p>; }`,
    ],
    invalid: [
        {
            code: `const stored = localStorage.getItem('appearance');`,
            errors: [{ message }],
        },
        {
            code: `document.documentElement.classList.add('dark');`,
            errors: [{ message }],
        },
        {
            code: `const mql = window.matchMedia('(max-width: 767px)');`,
            errors: [{ message }],
        },
        {
            code: `export function Page() { const ua = navigator.userAgent; return <p>{ua}</p>; }`,
            errors: [{ message }],
        },
        {
            code: `export default function Page() { return <p>{sessionStorage.getItem('a')}</p>; }`,
            errors: [{ message }],
        },
        {
            code: `export const useWidth = () => window.innerWidth;`,
            errors: [{ message }],
        },
        {
            code: `export const Page = memo(() => <p>{document.title}</p>);`,
            errors: [{ message }],
        },
        // A lazy state initialiser runs during render.
        {
            code: `export function Page() { const [w] = useState(() => window.innerWidth); return <p>{w}</p>; }`,
            errors: [{ message }],
        },
    ],
});
