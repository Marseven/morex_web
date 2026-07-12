<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="MR Money - Pilotez votre avenir financier">
    <meta name="theme-color" content="#005C53">

    <title inertia>{{ config('app.name', 'MR Money') }}</title>

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/favicon-180.png">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MR Money">

    <!-- Open Graph / Social -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="MR Money - Pilotez votre avenir financier">
    <meta property="og:description" content="Application de gestion de finances personnelles">
    <meta property="og:image" content="/images/favicon-180.png">

    <!-- Golos Text Font Preload -->
    <link rel="preload" href="/fonts/GolosText-Regular.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/fonts/GolosText-Medium.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/fonts/GolosText-Bold.ttf" as="font" type="font/ttf" crossorigin>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-theme-bg text-theme-text-primary">
    @inertia

    <!-- PWA : enregistrement du service worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function (error) {
                    console.warn('Service worker registration failed:', error);
                });
            });
        }
    </script>
</body>
</html>
