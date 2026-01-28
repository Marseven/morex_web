<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Morex - Pilotez votre avenir financier">
    <meta name="theme-color" content="#0D0D0D">

    <title inertia>{{ config('app.name', 'Morex') }}</title>

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" href="/images/Morex.png">
    <link rel="apple-touch-icon" href="/images/Morex.png">
    <link rel="shortcut icon" type="image/png" href="/images/Morex.png">

    <!-- Open Graph / Social -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Morex - Pilotez votre avenir financier">
    <meta property="og:description" content="Application de gestion de finances personnelles">
    <meta property="og:image" content="/images/Morex.png">

    <!-- Amazon Ember Font Preload -->
    <link rel="preload" href="/fonts/AmazonEmberDisplay_Rg.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/fonts/AmazonEmberDisplay_Md.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/fonts/AmazonEmberDisplay_Bd.ttf" as="font" type="font/ttf" crossorigin>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-theme-bg text-theme-text-primary">
    @inertia
</body>
</html>
