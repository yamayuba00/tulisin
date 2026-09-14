<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>503 - Sedang Dalam Pemeliharaan | {{ config('app.name') }}</title>
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
        .note {
            display: inline-block;
            margin-top: 24px;
            padding: 8px 14px;
            border: 1px solid #e5e5e5;
            border-radius: 999px;
            font-size: 13px;
            color: #737373;
            background: #ffffff;
        }
        .brand { font-size: 13px; color: #a3a3a3; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="code">503</div>
        <div class="title">Sedang Dalam Pemeliharaan</div>
        <p class="desc">Kami sedang melakukan pembaruan pada layanan {{ config('app.name') }}. Silakan kembali beberapa saat lagi.</p>
        <span class="note">Perkiraan selesai: ± 30 menit</span>
        <div class="brand">{{ config('app.name') }} · PT Kerja Tanpa Batas</div>
    </div>
</body>
</html>
