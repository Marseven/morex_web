<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="MR Money - Pilotez votre avenir financier">
    <meta name="theme-color" content="#0D0D0D">

    <title inertia>{{ config('app.name', 'MR Money') }}</title>

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" href="/images/MRMoney.png">
    <link rel="apple-touch-icon" href="/images/MRMoney.png">
    <link rel="shortcut icon" type="image/png" href="/images/MRMoney.png">

    <!-- Open Graph / Social -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="MR Money - Pilotez votre avenir financier">
    <meta property="og:description" content="Application de gestion de finances personnelles">
    <meta property="og:image" content="/images/MRMoney.png">

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
</body>
</html>
