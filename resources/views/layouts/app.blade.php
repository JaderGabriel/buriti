<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="BURI-TI — Tecnologia para Pessoas. Consultoria, desenvolvimento, BI, Power BI e gestão de projetos de TI.">
    <title>@yield('title', 'BURI-TI — Tecnologia para Pessoas')</title>
    <link rel="icon" href="{{ asset('images/logo-buriti.png') }}" type="image/png">
    <script>
        (() => {
            const theme = localStorage.getItem('buriti-theme');
            const dark = theme ? theme === 'dark' : false;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.classList.toggle('light', !dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-hidden">
    @yield('body')
</body>
</html>
