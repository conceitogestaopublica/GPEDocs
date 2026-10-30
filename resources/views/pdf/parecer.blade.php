<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Parecer - {{ $parecer->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; line-height: 1.5; padding: 30px 40px; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 12px; font-weight: bold; color: #0e7490; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { width: 150px; text-align: left; vertical-align: top; color: #555; font-size: 10px; padding: 3px 10px 3px 0; }
        table.dados td { padding: 3px 0; }
        .ementa { padding: 10px 14px; border-left: 3px solid #0e7490; background: #f1f5f9; font-style: italic; }
        .conteudo { font-size: 11px; line-height: 1.7; word-wrap: break-word; }
        @include('pdf.partials.estilo-texto-rico')
        .conclusao { display: inline-block; padding: 3px 12px; border: 1px solid #0e7490; color: #0e7490; font-weight: bold; }
        .assinatura { margin-top: 40px; text-align: center; }
        .assinatura .linha { width: 260px; margin: 0 auto 4px; border-top: 1px solid #333; }
        .verificacao { margin-top: 24px; text-align: center; font-size: 9px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Parecer', 'subtitulo' => $parecer->numero])

    <div class="section">
        <table class="dados">
            <tr><th>Parecer nº</th><td><strong>{{ $parecer->numero }}</strong></td></tr>
            <tr><th>Assunto</th><td><strong>{{ $parecer->assunto }}</strong></td></tr>
            @if($parecer->processo)<tr><th>Processo</th><td>{{ $parecer->processo->numero_protocolo }} — {{ $parecer->processo->assunto }}</td></tr>@endif
            @if($parecer->memorando)<tr><th>Memorando</th><td>{{ $parecer->memorando->numero }} — {{ $parecer->memorando->assunto }}</td></tr>@endif
            <tr><th>Parecerista</th><td>{{ $parecer->remetente?->name }}@if($parecer->setor_origem) — {{ $parecer->setor_origem }}@endif</td></tr>
            <tr><th>Data</th><td>{{ $parecer->enviado_em?->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>

    @if($parecer->ementa)
    <div class="section"><div class="section-title">Ementa</div><p class="ementa">{{ $parecer->ementa }}</p></div>
    @endif

    <div class="section">
        <div class="section-title">Parecer</div>
        <div class="conteudo conteudo-html">{!! $parecer->conteudoFinal() !!}</div>
    </div>

    <div class="section">
        <div class="section-title">Conclusão</div>
        <span class="conclusao">{{ \App\Models\Processo\Parecer::CONCLUSOES[$parecer->conclusao] ?? $parecer->conclusao }}</span>
    </div>

    @if($parecer->anexos->isNotEmpty())
    <div class="section">
        <div class="section-title">Anexos</div>
        <ul style="margin-left: 16px;">@foreach($parecer->anexos as $a)<li>{{ $a->nome }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="assinatura">
        <div class="linha"></div>
        {{ $parecer->remetente?->name }}<br>
        <span style="font-size: 9px; color: #555;">{{ $parecer->setor_origem }}</span>
    </div>

    <p class="verificacao">Verifique a autenticidade em {{ $qrCodeUrl }}</p>
</body>
</html>
