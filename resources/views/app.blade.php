<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) @if (($appearance ?? 'system') !== 'system') data-theme="{{ $appearance === 'dark' ? 'dark' : 'light' }}" @endif>
    <head>
        <meta charset="utf-8">
        {{-- TE-015: the per-request CSP nonce for scripts that add <style>/<script> at runtime (Vite, Inertia). --}}
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }

                    document.documentElement.dataset.theme = prefersDark ? 'dark' : 'light';
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: #f7f9fa; /* = --bg light (tokens.css; a test keeps them equal) */
            }

            html.dark {
                background-color: #0e1a20; /* = --bg dark */
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
