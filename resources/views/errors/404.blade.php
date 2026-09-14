<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>404 - Halaman Tidak Ditemukan | {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #fafafa;
            color: #171717;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .wrap { text-align: center; max-width: 480px; }
        .code {
            font-size: 96px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.02em;
            color: #171717;
        }
        .title { font-size: 22px; font-weight: 600; margin-top: 16px; }
        .desc { font-size: 15px; line-height: 1.6; color: #737373; margin-top: 8px; }
        .brand { font-size: 13px; color: #a3a3a3; margin-top: 32px; }
        .btn {
            display: inline-block;
            margin-top: 28px;
            padding: 12px 22px;
            border-radius: 12px;
            background: #171717;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s ease;
        }
        .btn:hover { background: #404040; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="code">404</div>
        <div class="title">Halaman Tidak Ditemukan</div>
        <p class="desc">Halaman yang kamu cari mungkin telah dipindahkan, dihapus, atau alamatnya salah ketik.</p>
        <a class="btn" href="{{ url('/') }}">Kembali ke Beranda</a>
        <div class="brand">{{ config('app.name') }} · PT Kerja Tanpa Batas</div>
    </div>
</body>
</html>
