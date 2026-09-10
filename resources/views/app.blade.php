<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Primary SEO --}}
        <title>{{ config('app.name') }} — Platform Penulisan Akademik Berbasis AI</title>
        <meta name="description" content="Tulisin — platform penulisan akademik berbasis AI. Susun skripsi, tesis, makalah, dan jurnal dengan canvas blok, asisten AI, Turnitin AI Optimizer, serta format kampus otomatis.">
        <meta name="keywords" content="Tulisin, skripsi, tesis, disertasi, makalah, jurnal, penulisan akademik, asisten AI, Turnitin AI Optimizer, plagiarism optimizer, mahasiswa, kampus, format kampus, daftar pustaka">
        <meta name="author" content="Tulisin">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <link rel="canonical" href="{{ config('app.url') }}">

        {{-- Favicon --}}
        <link rel="icon" href="/img/favicon.png">
        <link rel="apple-touch-icon" href="/img/favicon.png">

        {{-- Theme / PWA --}}
        <meta name="theme-color" content="#0a0a0a">

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ config('app.name') }} — Platform Penulisan Akademik Berbasis AI">
        <meta property="og:description" content="Tulisin — platform penulisan akademik berbasis AI. Susun skripsi, tesis, makalah, dan jurnal dengan canvas blok, asisten AI, serta format kampus otomatis.">
        <meta property="og:url" content="{{ config('app.url') }}">
        <meta property="og:image" content="{{ config('app.url') }}/og-image.png">
        <meta property="og:locale" content="id_ID">
        <meta property="og:locale:alternate" content="en_US">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@@tulisin">
        <meta name="twitter:title" content="{{ config('app.name') }} — Platform Penulisan Akademik Berbasis AI">
        <meta name="twitter:description" content="Tulisin — platform penulisan akademik berbasis AI. Susun skripsi, tesis, makalah, dan jurnal dengan canvas blok, asisten AI, serta format kampus otomatis.">
        <meta name="twitter:image" content="{{ config('app.url') }}/og-image.png">

        {{-- Structured data (JSON-LD) --}}
        @php
            $seoSiteUrl = rtrim((string) config('app.url'), '/');
            $seoStructuredData = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $seoSiteUrl . '/#organization',
                        'name' => config('app.name'),
                        'url' => $seoSiteUrl,
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $seoSiteUrl . '/img/favicon.png',
                        ],
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $seoSiteUrl . '/#website',
                        'url' => $seoSiteUrl,
                        'name' => config('app.name'),
                        'publisher' => ['@id' => $seoSiteUrl . '/#organization'],
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($seoStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>

        <script>
            (function () {
                const theme = localStorage.getItem('theme') || 'light';
                if (theme === 'dark') document.documentElement.classList.add('dark');
            })();
        </script>

        {{-- Google Tag Manager --}}
        @if (config('services.google_tag_manager_id'))
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ config('services.google_tag_manager_id') }}');
        </script>
        @endif
        {{-- End Google Tag Manager --}}

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Splash loader statis: tampil segera sebelum bundle Vue dimuat & di-mount --}}
        <style>
            .app-splash {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #ffffff;
                transition: opacity 0.3s ease;
            }
            html.dark .app-splash { background: #0a0a0a; }

            .app-splash__inner {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
            }

            .app-splash__logo {
                width: 56px;
                height: 56px;
                border-radius: 16px;
                background: #0a0a0a;
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: app-splash-pop 1.4s ease-in-out infinite;
            }
            html.dark .app-splash__logo { background: #ffffff; color: #0a0a0a; }
            .app-splash__logo svg { width: 28px; height: 28px; }

            .app-splash__brand {
                font-size: 20px;
                font-weight: 700;
                letter-spacing: -0.02em;
                color: #0a0a0a;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            }
            html.dark .app-splash__brand { color: #ffffff; }

            .app-splash__bar {
                width: 160px;
                height: 4px;
                border-radius: 999px;
                background: #e5e5e5;
                overflow: hidden;
            }
            html.dark .app-splash__bar { background: #27272a; }
            .app-splash__bar span {
                display: block;
                height: 100%;
                width: 40%;
                border-radius: 999px;
                background: #0a0a0a;
                animation: app-splash-slide 1.1s ease-in-out infinite;
            }
            html.dark .app-splash__bar span { background: #ffffff; }

            @keyframes app-splash-slide {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(360%); }
            }
            @keyframes app-splash-pop {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(0.9); }
            }
        </style>
    </head>
    <body>
        {{-- Google Tag Manager (noscript) --}}
        @if (config('services.google_tag_manager_id'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.google_tag_manager_id') }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        {{-- End Google Tag Manager (noscript) --}}

        <div id="app">
            <div class="app-splash">
                <div class="app-splash__inner">
                    <div class="app-splash__logo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"></path>
                            <path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"></path>
                        </svg>
                    </div>
                    <div class="app-splash__brand">Tulisin</div>
                    <div class="app-splash__bar"><span></span></div>
                </div>
            </div>
        </div>
    </body>
</html>
