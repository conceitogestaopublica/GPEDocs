<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>GPE Cloud — Painel Super-Admin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white mb-3 shadow">
                <i class="fas fa-building-shield text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">GPE Cloud</h1>
            <p class="text-sm text-slate-500">Painel de super-administradores</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            @if($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 mb-4 text-sm">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            @if(request('erro'))
                <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 mb-4 text-sm">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    {{ request('erro') }}
                </div>
            @endif

            @if(session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 mb-4 text-sm">
                    <i class="fas fa-check-circle mr-1"></i>{{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('landlord.login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fas fa-envelope text-sm"></i>
                        </span>
                        <input id="email" name="email" type="email" required autofocus
                               value="{{ old('email') }}"
                               class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="seu@email.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-slate-600 mb-1">Senha</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input id="password" name="password" type="password" required
                               class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 text-white rounded-lg py-2.5 text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                    <i class="fas fa-right-to-bracket mr-1"></i>Entrar
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-4">
            Acesso restrito. Tenant users devem usar o subdomínio do município.
        </p>
    </div>
</body>
</html>