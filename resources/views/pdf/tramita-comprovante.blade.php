<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante do trâmite {{ $t->idt }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.45; padding: 30px 40px; }
        h2 { text-align: center; font-size: 15px; margin: 20px 0 14px; }
        h3 { font-size: 12px; margin: 16px 0 6px; color: #0e7490; }
        table { width: 100%; border-collapse: collapse; }
        table.dados th { width: 28%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 5px 8px; border: 1px solid #cbd5e1; font-size: 10px; color: #475569; }
        table.dados td { padding: 5px 8px; border: 1px solid #cbd5e1; }
        table.arquivos td, table.arquivos th { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10px; text-align: left; }
        .hash { font-family: 'Courier', monospace; font-size: 8px; color: #555; word-break: break-all; }
        .destaque { font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    @php $fuso = 'America/Sao_Paulo'; $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v, 'UTC')->setTimezone($fuso)->format('d/m/Y H:i:s') : '—'; @endphp
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => 'Comprovante de trâmite', 'subtitulo' => 'Tramita GOV.BR', 'usuario' => $usuario])

    <h2>Comprovante de trâmite — Tramita GOV.BR</h2>
    <table class="dados">
        <tr><th>Sentido</th><td>{{ $t->sentido === 'enviado' ? 'Enviado a outro ente' : 'Recebido de outro ente' }}</td></tr>
        <tr><th>Documento</th><td>{{ $t->assunto }}</td></tr>
        <tr><th>{{ $t->sentido === 'enviado' ? 'Destino' : 'Origem' }}</th><td>{{ $t->outro_nome }} @if($t->outro_estrutura)(estrutura {{ $t->outro_estrutura }})@endif</td></tr>
        <tr><th>NRE</th><td class="destaque">{{ $t->nre ?? '—' }}</td></tr>
        <tr><th>IDT</th><td class="destaque">{{ $t->idt ?? '—' }}</td></tr>
        <tr><th>{{ $t->sentido === 'enviado' ? 'Enviado em' : 'Chegou em' }}</th><td>{{ $dt($t->enviado_em) }}</td></tr>
        <tr><th>Situação</th><td>{{ $linha['status_rotulo'] }}@if($t->recusa_motivo) — {{ $linha['recusa'] }}@if($t->recusa_justificativa): {{ $t->recusa_justificativa }}@endif @endif</td></tr>
        <tr><th>{{ $t->status === 'recusado' ? 'Recusado em' : 'Recibo em' }}</th><td>{{ isset($recibo['data_hora']) ? \Illuminate\Support\Carbon::parse($recibo['data_hora'])->setTimezone($fuso)->format('d/m/Y H:i:s') : $dt($t->concluido_em) }}</td></tr>
        @if (! empty($recibo['recebido_por']))<tr><th>Recebido por</th><td>{{ $recibo['recebido_por'] }}</td></tr>@endif
    </table>

    <h3>Arquivos ({{ count($componentes) }})</h3>
    <table class="arquivos">
        <tr><th>Arquivo</th><th>Tamanho</th><th>Hash SHA-256</th></tr>
        @foreach ($componentes as $c)
            <tr><td>{{ $c['nome'] }}</td><td>{{ number_format(($c['tamanho'] ?? 0) / 1024, 1, ',', '.') }} KB</td><td class="hash">{{ $c['hash'] }}</td></tr>
        @endforeach
    </table>
</body>
</html>
