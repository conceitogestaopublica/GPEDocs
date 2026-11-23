<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo-gpe-docs-favicon.svg?v=2" type="image/svg+xml">
    <link rel="icon" href="/images/logo-gpe-docs-favicon-32.png?v=2" sizes="32x32" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
    {{-- Cor predominante do município (TR 4.22.1) --}}
    {!! \App\Support\IdentidadeVisual::css() !!}
</head>
<body class="h-full bg-gray-50 antialiased">
    @inertia
</body>
</html>
