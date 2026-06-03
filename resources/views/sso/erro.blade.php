<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SSO — {{ $titulo }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-rose-100 text-rose-600 mb-4">
                <i class="fas fa-triangle-exclamation text-2xl"></i>
            </div>

            <h1 class="text-xl font-bold text-slate-800 mb-1">{{ $titulo }}</h1>

            <div class="text-xs font-mono text-slate-400 mb-4">{{ $codigo }}</div>

            <p class="text-sm text-slate-600 mb-6">{{ $mensagem }}</p>

            @if($adminEmail && $tenantEmail)
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-left text-xs text-slate-600 mb-6 space-y-1">
                    <div><span class="font-medium text-slate-500">Super-admin:</span> <span class="font-mono">{{ $adminEmail }}</span></div>
                    <div><span class="font-medium text-slate-500">Email procurado no tenant:</span> <span class="font-mono">{{ $tenantEmail }}</span></div>
                </div>
            @endif

            <div class="flex flex-col gap-2">
                @foreach($opcoes as $opcao)
                    <a href="{{ $opcao['url'] }}"
                       class="block w-full rounded-lg py-2.5 text-sm font-medium transition
                              {{ $opcao['tipo'] === 'primary'
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200' }}">
                        {{ $opcao['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 mt-4">
            Se o problema persistir, contate o suporte com o código acima.
        </p>
    </div>
</body>
</html>
