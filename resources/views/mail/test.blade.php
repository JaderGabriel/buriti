<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>E-mail de teste — BURI-TI</title>
</head>
<body style="margin:0;padding:24px;background:#0a1628;color:#e8eef6;font-family:Manrope,Segoe UI,sans-serif;">
    <p style="letter-spacing:.18em;text-transform:uppercase;font-size:11px;color:#e24b35;">BURI-TI</p>
    <h1 style="font-size:22px;margin:8px 0 12px;">E-mail de teste</h1>
    <p style="line-height:1.5;color:#b7c2d0;">
        Se você recebeu esta mensagem, o SMTP do painel está a enviar. Nenhum convite de depoimento é disparado automaticamente — o admin copia o link e envia pelo canal que preferir.
    </p>
    <p style="font-size:12px;color:#8b98a8;">Enviado em {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p>
</body>
</html>
