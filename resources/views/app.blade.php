@use('App\Enums\Appearance')
@php($theme = ($appearance ?? null) instanceof Appearance ? $appearance : Appearance::System)
<!DOCTYPE html>
{{-- TE-018: an explicit theme is rendered on <html> by the server; only `system` needs the script below. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $theme === Appearance::Dark]) @if ($theme !== Appearance::System) data-theme="{{ $theme->value }}" @endif>
    <head>
        <meta charset="utf-8">
        {{-- TE-015: the per-request CSP nonce for scripts that add <style>/<script> at runtime (Vite, Inertia). --}}
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="{{ $theme->colorScheme() }}">

        @if ($theme === Appearance::System)
            {{-- TE-018 AC1: apply the system theme before first paint; use-appearance keeps following it afterwards. --}}
            <script nonce="{{ Vite::cspNonce() }}">(function(){var d=document.documentElement,t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';d.classList.toggle('dark',t==='dark');d.dataset.theme=t;})();</script>
        @endif

        {{-- The page background before the CSS loads (= --bg in tokens.css; light, dark, and dark without JavaScript). --}}
        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: #f7f9fa;
            }

            html.dark,
            html[data-theme='dark'] {
                background-color: #0e1a20;
            }

            @media (prefers-color-scheme: dark) {
                html:not([data-theme]):not(.dark) {
                    background-color: #0e1a20;
                }
            }
        </style>

        {{-- TE-020: preload the primary (latin) Figtree face; same-origin, but fonts always need crossorigin. --}}
        <link rel="preload" href="{{ Vite::asset('resources/fonts/figtree-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
