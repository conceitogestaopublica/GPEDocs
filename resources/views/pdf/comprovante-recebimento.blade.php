<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante {{ $r->codigo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #222; line-height: 1.5; padding: 30px 40px; }
        h2 { text-align: center; font-size: 16px; margin: 24px 0 4px; }
        .codigo { text-align: center; font-size: 13px; color: #0e7490; font-weight: bold; margin-bottom: 18px; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { width: 30%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 7px 10px; border: 1px solid #cbd5e1; font-size: 11px; color: #475569; }
        table.dados td { padding: 7px 10px; border: 1px solid #cbd5e1; }
        .assinaturas { margin-top: 60px; width: 100%; }
        .assinaturas td { width: 50%; text-align: center; padding: 0 20px; font-size: 11px; }
        .linha { border-top: 1px solid #333; padding-top: 4px; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => 'Comprovante de recebimento', 'subtitulo' => $processo->numero_protocolo, 'usuario' => $r->servidor])

    <h2>Comprovante de recebimento de documento físico</h2>
    <p class="codigo">{{ $r->codigo }}</p>

    <table class="dados">
        <tr><th>Processo</th><td>{{ $processo->numero_protocolo }} — {{ $processo->assunto }}</td></tr>
        <tr><th>Assunto</th><td>{{ $processo->tipoProcesso?->nome ?? '-' }}</td></tr>
        <tr><th>Documento recebido</th><td>{{ $r->descricao }}</td></tr>
        <tr><th>Folhas</th><td>{{ $r->folhas ?? '-' }}</td></tr>
        <tr><th>Entregue por</th><td>{{ $r->entregue_por_nome }}@if($r->entregue_por_documento) — {{ $r->entregue_por_documento }}@endif</td></tr>
        <tr><th>Recebido por</th><td>{{ $r->servidor }}@if($setor) — {{ $setor->nome }}@if($setor->sigla) ({{ $setor->sigla }})@endif @endif</td></tr>
        <tr><th>Data e hora</th><td>{{ \Illuminate\Support\Carbon::parse($r->created_at, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
    </table>

    <table class="assinaturas">
        <tr>
            <td><p class="linha">{{ $r->entregue_por_nome }}<br>Quem entregou</p></td>
            <td><p class="linha">{{ $r->servidor }}<br>Quem recebeu</p></td>
        </tr>
    </table>
</body>
</html>
