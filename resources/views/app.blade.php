<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'KOPDES Merah Putih') }}</title>

        <!-- Fonts: Plus Jakarta Sans (UI & Heading) + JetBrains Mono (Financial Numbers & Codes) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Conditional Midtrans Snap JS Loader -->
        @if (filled(config('services.midtrans.client_key')))
            <script
                type="text/javascript"
                src="{{ config('services.midtrans.snap_js_url', 'https://app.sandbox.midtrans.com/snap/snap.js') }}"
                data-client-key="{{ config('services.midtrans.client_key') }}"
            ></script>
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        @inertia
    </body>
</html>
