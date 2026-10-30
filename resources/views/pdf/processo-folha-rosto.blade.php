<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de rosto - {{ $p->numero_protocolo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #222; line-height: 1.5; padding: 30px 40px; }
        .numero { text-align: center; margin: 40px 0 6px; font-size: 28px; font-weight: bold; color: #0e7490; letter-spacing: 1px; }
        .tipo { text-align: center; font-size: 13px; color: #555; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 30px; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.dados th { width: 28%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 11px; color: #475569; }
        table.dados td { padding: 8px 10px; border: 1px solid #cbd5e1; }
        .assunto { font-size: 15px; font-weight: bold; }
        ul { margin-left: 16px; }
        .verificacao { margin-top: 30px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Folha de rosto', 'subtitulo' => $p->numero_protocolo])

    <p class="numero">{{ $p->numero_protocolo }}</p>
    <p class="tipo">{{ $p->tipoProcesso?->nome ?? 'Processo' }}</p>

    <table class="dados">
        <tr><th>Assunto</th><td class="assunto">{{ $p->assunto }}</td></tr>
        <tr><th>Requerente</th><td>{{ $p->requerente_nome ?: '-' }}@if($p->requerente_cpf) — CPF {{ $p->requerente_cpf }}@endif</td></tr>
        @if($solicitante)<tr><th>Aberto em nome de</th><td>{{ $solicitante->name }}</td></tr>@endif
        @if($p->patrimonio)<tr><th>Patrimônio</th><td>{{ $p->patrimonio }}</td></tr>@endif
        <tr><th>Aberto por</th><td>{{ $p->abertoPor?->name ?? ($p->setor_origem ?: '-') }}</td></tr>
        <tr><th>Data de abertura</th><td>{{ $p->created_at?->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Situação</th><td>{{ ucfirst(str_replace('_', ' ', $p->status)) }}@if($setor_atual) · está em {{ $setor_atual }}@endif · {{ $tramitacoes }} tramitação(ões)</td></tr>
        <tr><th>Anexos</th><td>
            @if($p->anexos->isEmpty()) Nenhum
            @else <ul>@foreach($p->anexos as $a)<li>{{ $a->nome }}</li>@endforeach</ul>
            @endif
        </td></tr>
    </table>

    <p class="verificacao">Consulte o andamento no GPE Docs pelo número {{ $p->numero_protocolo }}.</p>
</body>
</html>
