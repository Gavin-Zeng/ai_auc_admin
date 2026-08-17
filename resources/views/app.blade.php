<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-appearance="{{ $appearance }}"
    data-appearance-cookie="{{ $appearanceCookieState }}"
    data-ui-theme="{{ $uiTheme }}"
    data-theme-brand="{{ $themeBrand }}"
    data-theme-neutral="{{ $themeNeutral }}"
    data-theme-radius="{{ $themeRadius }}"
    data-theme-density="{{ $themeDensity }}"
    @class(['dark' => $appearance === 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script src="{{ asset('theme-bootstrap.js') }}"></script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
