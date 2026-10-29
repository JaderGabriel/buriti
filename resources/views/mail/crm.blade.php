<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine ?? 'BURI-TI' }}</title>
    <style>
        .mail-quote, .mail-quote p {
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 13px !important;
            line-height: 1.5 !important;
            color: #6a7787 !important;
            font-weight: 400 !important;
            font-style: normal !important;
        }
        .mail-quote {
            margin: 18px 0 0 !important;
            padding: 4px 0 2px 14px !important;
            border-left: 2px solid #c5d0dc !important;
        }
        .mail-quote__meta {
            font-size: 12px !important;
            color: #8a96a3 !important;
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef2f6;color:#1b2430;font-family:Georgia,'Times New Roman',serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #d9e2ec;">
                    <tr>
                        <td style="padding:18px 28px;background:#0f2744;color:#ffffff;">
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#9ec4ea;">BURI-TI</p>
                            <p style="margin:6px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#ffffff;">{{ $fromName ?: 'BURI-TI' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:16px;line-height:1.7;color:#1b2430;">
                            <div style="font-size:16px;line-height:1.7;color:#1b2430;">{!! $bodyHtml !!}</div>
                        </td>
                    </tr>
                    @if(! empty($signatureBlock))
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #1a5f9e;">
                                <tr>
                                    <td style="padding-top:18px;">
                                        @include('mail.partials.signature', ['block' => $signatureBlock, 'siteUrl' => $siteUrl, 'siteHost' => $siteHost])
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:18px 28px 24px;background:#f7f9fc;border-top:1px solid #e4ebf2;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.55;color:#6a7787;">
                            {!! nl2br(e($legalNotice)) !!}
                            <p style="margin:12px 0 0;">
                                <a href="{{ $privacyUrl }}" style="color:#1a5f9e;text-decoration:underline;">Política de Privacidade</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
