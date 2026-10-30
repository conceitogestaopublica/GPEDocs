<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#155e75">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fiscalização{{ $orgao ? ' — ' . $orgao : '' }}</title>
    {{-- App offline da fiscalização (TR 4.52): instalável no celular, abre sem internet --}}
    <link rel="manifest" href="/fiscal/manifesto">
    <link rel="icon" href="/fiscal/icone" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/fiscal/icone">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @viteReactRefresh
    @vite(['resources/js/fiscal/main.jsx'])
</head>
<body class="bg-slate-50 antialiased">
    <div id="fiscal"></div>
</body>
</html>
