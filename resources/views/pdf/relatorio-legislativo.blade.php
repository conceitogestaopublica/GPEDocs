<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Protocolo legislativo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #222; line-height: 1.4; padding: 24px 30px; }
        h2 { font-size: 14px; margin: 10px 0 6px; }
        .filtros { font-size: 10px; color: #475569; margin-bottom: 8px; }
        table.ind { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.ind td { border: 1px solid #cbd5e1; padding: 6px; text-align: center; }
        table.ind b { display: block; font-size: 15px; color: #0e7490; }
        table.lista { width: 100%; border-collapse: collapse; }
        table.lista th { background: #f1f5f9; text-align: left; padding: 5px; border: 1px solid #cbd5e1; font-size: 9px; color: #475569; }
        table.lista td { padding: 5px; border: 1px solid #e2e8f0; vertical-align: top; }
        .vencido { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Protocolo legislativo', 'subtitulo' => 'Relatório gerencial'])

    <h2>Requerimentos e indicações de vereadores</h2>
    <p class="filtros">
        @forelse($filtros as $rotulo => $valor){{ $rotulo }}: <b>{{ $valor }}</b>@if(! $loop->last) · @endif
        @empty Sem filtros — todas as proposições.
        @endforelse
    </p>

    <table class="ind">
        <tr>
            <td><b>{{ $ind['total'] }}</b>Total</td>
            <td><b>{{ $ind['recebidas'] }}</b>Sem resposta</td>
            <td><b>{{ $ind['respondidas'] }}</b>Respondidas</td>
            <td><b>{{ $ind['vencendo'] }}</b>Vencendo (5 dias)</td>
            <td><b>{{ $ind['vencidas'] }}</b>Vencidas</td>
            <td><b>{{ $ind['pct_no_prazo'] !== null ? $ind['pct_no_prazo'] . '%' : '-' }}</b>Respondidas no prazo</td>
        </tr>
    </table>

    <table class="lista">
        <thead><tr><th>Protocolo</th><th>Proposição</th><th>Vereador</th><th>Assunto</th><th>Recebida</th><th>Prazo</th><th>Respondida</th><th>Situação do prazo</th></tr></thead>
        <tbody>
        @forelse($linhas as $l)
            <tr>
                <td>{{ $l['numero'] }}</td>
                <td>{{ $l['proposicao'] }}@if($l['proposicao_numero']) nº {{ $l['proposicao_numero'] }}@endif</td>
                <td>{{ $l['vereador'] }}@if($l['partido']) ({{ $l['partido'] }})@endif</td>
                <td>{{ $l['assunto'] }}</td>
                <td>{{ $data($l['recebido_em']) }}</td>
                <td>{{ $data($l['prazo']['vence_em'] ?? null) }}</td>
                <td>{{ $data($l['respondido_em']) }}</td>
                <td class="{{ in_array($l['prazo']['situacao'] ?? '', ['vencido', 'fora_do_prazo'], true) ? 'vencido' : '' }}">{{ $situacao[$l['prazo']['situacao'] ?? ''] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align: center; color: #64748b;">Nenhuma proposição com estes filtros.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
