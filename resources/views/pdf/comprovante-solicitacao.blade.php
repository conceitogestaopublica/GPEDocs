<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante {{ $solicitacao->codigo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #222; line-height: 1.5; padding: 30px 40px; }
        .codigo { text-align: center; margin: 30px 0 4px; font-size: 26px; font-weight: bold; color: #0e7490; letter-spacing: 1px; }
        .sub { text-align: center; font-size: 12px; color: #555; margin-bottom: 24px; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.dados th { width: 30%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 7px 10px; border: 1px solid #cbd5e1; font-size: 11px; color: #475569; }
        table.dados td { padding: 7px 10px; border: 1px solid #cbd5e1; }
        ul { margin-left: 16px; }
        .nota { margin-top: 18px; font-size: 11px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => 'Comprovante de solicitação', 'subtitulo' => $solicitacao->codigo, 'usuario' => $solicitacao->cidadao?->nome])

    <p class="codigo">{{ $solicitacao->codigo }}</p>
    <p class="sub">Guarde este código para acompanhar a sua solicitação</p>

    <table class="dados">
        <tr><th>Serviço</th><td>{{ $solicitacao->servico?->titulo ?? '-' }}</td></tr>
        <tr><th>Solicitante</th><td>{{ $solicitacao->cidadao?->nome ?? 'Anônimo' }}@if($solicitacao->cidadao?->cpf) — CPF {{ \App\Support\DocumentoPessoa::mascararCpf($solicitacao->cidadao->cpf) }}@endif</td></tr>
        <tr><th>Aberta em</th><td>{{ $solicitacao->created_at?->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Setor que vai atender</th><td>{{ $resumo['setor_destino']['nome'] ?? 'A definir pelo atendimento' }}@if(! empty($resumo['setor_destino']['sigla'])) ({{ $resumo['setor_destino']['sigla'] }})@endif</td></tr>
        <tr><th>Processo</th><td>{{ $resumo['processo_numero'] ?? 'Será aberto pelo atendimento' }}</td></tr>
        <tr><th>Situação</th><td>{{ $resumo['status_rotulo'] }}</td></tr>
        <tr><th>Anexos</th><td>
            @if($solicitacao->anexos->isEmpty()) Nenhum
            @else <ul>@foreach($solicitacao->anexos as $a)<li>{{ $a->nome }}</li>@endforeach</ul>
            @endif
        </td></tr>
    </table>

    <p class="nota">Acompanhe e responda pelo portal: {{ $link }}</p>
</body>
</html>
