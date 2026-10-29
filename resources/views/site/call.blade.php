<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="refresh" content="0;url={{ $telHref }}">
    <title>Ligar — BURI-TI</title>
    <script>location.replace(@json($telHref));</script>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0f2744; color: #eef2f6; font-family: Arial, Helvetica, sans-serif; }
        a { color: #9ec4ea; }
        p { margin: 0 0 12px; }
    </style>
</head>
<body>
    <main style="text-align:center;padding:24px;">
        <p>A abrir o telefone…</p>
        <p><a href="{{ $telHref }}">Ligar agora</a></p>
    </main>
</body>
</html>
