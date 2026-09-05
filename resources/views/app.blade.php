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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
