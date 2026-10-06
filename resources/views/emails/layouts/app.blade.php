<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; color:#20242a; font-family:Arial,Helvetica,sans-serif; line-height:1.6;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $emailTitle ?? config('app.name') }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px; background:#ffffff; border:1px solid #e4e7eb; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="background:#111111; padding:24px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="display:inline-block; background:#ffffff; border-radius:8px; padding:6px 9px;">
                                            <img src="{{ rtrim(config('app.frontend_url'), '/') }}/logo.png" width="150" alt="{{ config('app.name') }}" style="display:block; width:150px; max-width:100%; height:auto; border:0;">
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align:middle; color:#e5a817; font-size:11px; font-weight:bold; letter-spacing:1.4px; text-transform:uppercase;">
                                        {{ config('app.name') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 34px 28px;">
                            <h1 style="margin:0 0 22px; color:#15181c; font-size:24px; line-height:1.25; font-weight:700;">
                                {{ $emailTitle ?? config('app.name') }}
                            </h1>
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #edf0f2; padding:20px 28px; background:#fafbfc; color:#7b838d; font-size:12px; text-align:center;">
                            <p style="margin:0 0 5px;">{{ config('app.name') }}</p>
                            <p style="margin:0;">Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                        </td>
                    </tr>
                </table>
                <p style="max-width:620px; margin:14px 0 0; color:#9aa1aa; font-size:11px; text-align:center;">
                    © {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
