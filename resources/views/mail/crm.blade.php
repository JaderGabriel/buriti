<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine ?? 'BURI-TI' }}</title>
</head>
<body style="margin:0;padding:24px;background:#0a1628;color:#e8eef6;font-family:Manrope,Segoe UI,sans-serif;line-height:1.55;">
    <div style="white-space:pre-wrap;">{!! nl2br(e($body)) !!}</div>
    @if(filled($signature))
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid #24344a;color:#b7c2d0;white-space:pre-wrap;font-size:13px;">{!! nl2br(e($signature)) !!}</div>
    @endif
</body>
</html>
