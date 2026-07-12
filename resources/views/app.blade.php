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

    <!-- PWA : invite d'installation (Android/Chrome via beforeinstallprompt, iOS via instructions) -->
    <style>
        #pwa-install {
            position: fixed;
            left: 12px; right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            z-index: 2147483000;
            display: none;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 16px;
            background: #003a34;
            color: #e6fffb;
            border: 1px solid rgba(53, 224, 201, .25);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .45);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        #pwa-install.pwa-show { display: flex; animation: pwa-rise .3s ease; }
        @keyframes pwa-rise { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        #pwa-install img { width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0; }
        #pwa-install .pwa-text { flex: 1; min-width: 0; font-size: 13px; line-height: 1.4; }
        #pwa-install .pwa-text strong { display: block; font-size: 14px; margin-bottom: 2px; color: #fff; }
        #pwa-install .pwa-text small { color: #9fded6; }
        #pwa-install button { border: none; cursor: pointer; font-family: inherit; }
        #pwa-install .pwa-cta {
            background: #35e0c9; color: #003a34; font-weight: 700; font-size: 13px;
            padding: 9px 16px; border-radius: 10px; flex-shrink: 0;
        }
        #pwa-install .pwa-close {
            background: transparent; color: #7fc9bf; font-size: 20px; line-height: 1;
            padding: 4px 6px; flex-shrink: 0;
        }
        @media (min-width: 640px) { #pwa-install { left: auto; right: 20px; max-width: 380px; } }
    </style>
    <script>
        (function () {
            var STANDALONE = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            if (STANDALONE) return; // déjà installée

            var DISMISS_KEY = 'pwa-install-dismissed';
            var last = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
            if (last && (Date.now() - last) < 7 * 24 * 3600 * 1000) return; // masquée 7 jours après un refus

            var ua = navigator.userAgent || '';
            var isIos = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;
            var isSafari = /safari/i.test(ua) && !/crios|fxios|chrome|android/i.test(ua);
            var deferred = null;
            var el = null;

            function build(mode) {
                if (el) return el;
                el = document.createElement('div');
                el.id = 'pwa-install';
                var body = mode === 'ios'
                    ? '<div class="pwa-text"><strong>Installer MR Money</strong>'
                        + '<small>Appuyez sur <span aria-hidden="true">&#x2191;</span> Partager, puis « Sur l\'écran d\'accueil ».</small></div>'
                    : '<div class="pwa-text"><strong>Installer MR Money</strong>'
                        + '<small>Ajoutez l\'app à votre écran d\'accueil.</small></div>'
                        + '<button type="button" class="pwa-cta">Installer</button>';
                el.innerHTML = '<img src="/images/pwa-192.png" alt="">' + body
                    + '<button type="button" class="pwa-close" aria-label="Fermer">&times;</button>';
                document.body.appendChild(el);

                el.querySelector('.pwa-close').addEventListener('click', function () {
                    localStorage.setItem(DISMISS_KEY, String(Date.now()));
                    hide();
                });
                var cta = el.querySelector('.pwa-cta');
                if (cta) cta.addEventListener('click', function () {
                    if (!deferred) return;
                    deferred.prompt();
                    deferred.userChoice.then(function () { deferred = null; hide(); });
                });
                return el;
            }
            function show(mode) { build(mode).classList.add('pwa-show'); }
            function hide() { if (el) el.classList.remove('pwa-show'); }

            // Android / Chrome / Edge : capter l'événement et proposer notre bouton
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferred = e;
                show('android');
            });

            // iOS Safari : aucun prompt natif → afficher les instructions manuelles
            if (isIos && isSafari) {
                window.addEventListener('load', function () { setTimeout(function () { show('ios'); }, 2500); });
            }

            window.addEventListener('appinstalled', function () {
                localStorage.setItem(DISMISS_KEY, String(Date.now()));
                hide();
            });
        })();
    </script>
</body>
</html>
