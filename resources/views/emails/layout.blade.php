<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tulisin')</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
                    <tr>
                        <td style="padding:24px 32px;background-color:#171717;">
                            <span style="font-size:18px;font-weight:bold;color:#ffffff;">Tulisin</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;border-top:1px solid #f4f4f5;color:#a1a1aa;font-size:12px;line-height:1.6;">
                            Best Regards,<br>
                            <strong style="color:#3f3f46;">Tim Tulisin</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
