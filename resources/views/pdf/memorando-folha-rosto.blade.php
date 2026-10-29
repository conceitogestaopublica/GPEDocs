<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de rosto - {{ $memorando->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #222; line-height: 1.5; padding: 30px 40px; }
        .numero { text-align: center; margin: 40px 0 6px; font-size: 28px; font-weight: bold; color: #0e7490; letter-spacing: 1px; }
        .tipo { text-align: center; font-size: 13px; color: #555; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 30px; }
        .privado { text-align: center; margin-bottom: 20px; }
        .privado span { border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 4px 14px; letter-spacing: 2px; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.dados th { width: 28%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 11px; color: #475569; }
        table.dados td { padding: 8px 10px; border: 1px solid #cbd5e1; }
        .assunto { font-size: 15px; font-weight: bold; }
        ul { margin-left: 16px; }
        .verificacao { margin-top: 30px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Folha de rosto', 'subtitulo' => $memorando->numero, 'usuario' => $usuario ?? null])

    <p class="numero">{{ $memorando->numero }}</p>
    <p class="tipo">Memorando interno</p>
    @if($memorando->confidencial)<p class="privado"><span>PRIVADO</span></p>@endif

    <table class="dados">
        <tr><th>Assunto</th><td class="assunto">{{ $memorando->assunto }}</td></tr>
        <tr><th>De</th><td>{{ $memorando->remetente?->name ?? '-' }}@if($memorando->setor_origem) — {{ $memorando->setor_origem }}@endif</td></tr>
        <tr><th>Para</th><td>
            @foreach($memorando->destinatarios as $dest){{ $dest->usuario?->name ?? $dest->unidade?->nome ?? $dest->setor_destino ?? '-' }}{{ ! $loop->last ? ', ' : '' }}@endforeach
        </td></tr>
        <tr><th>Data</th><td>{{ $memorando->created_at?->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Situação</th><td>{{ $memorando->status === 'arquivado' ? 'Arquivado' : 'Em andamento' }} · {{ $despachos }} despacho(s)</td></tr>
        <tr><th>Anexos</th><td>
            @if($memorando->anexos->isEmpty()) Nenhum
            @else <ul>@foreach($memorando->anexos as $a)<li>{{ $a->nome }}</li>@endforeach</ul>
            @endif
        </td></tr>
    </table>

    @if($memorando->qr_code_token)
    <p class="verificacao">Verifique a autenticidade em {{ config('app.url') }}/verificar/{{ $memorando->qr_code_token }}</p>
    @endif
</body>
</html>
