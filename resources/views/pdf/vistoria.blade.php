<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de vistoria {{ $v->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.45; padding: 30px 40px; }
        h2 { text-align: center; font-size: 15px; margin: 20px 0 14px; }
        h3 { font-size: 12px; margin: 16px 0 6px; color: #0e7490; }
        table { width: 100%; border-collapse: collapse; }
        table.dados th { width: 28%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 5px 8px; border: 1px solid #cbd5e1; font-size: 10px; color: #475569; }
        table.dados td { padding: 5px 8px; border: 1px solid #cbd5e1; }
        table.itens td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .grupo td { background: #f1f5f9; font-weight: bold; font-size: 10px; }
        .r { width: 22%; text-align: right; font-weight: bold; }
        .atende { color: #047857; } .nao_atende { color: #b91c1c; } .nao_se_aplica { color: #64748b; }
        .obs { color: #555; font-size: 10px; }
        .fotos td { width: 50%; padding: 6px; vertical-align: top; text-align: center; }
        .fotos img { max-width: 100%; max-height: 260px; }
        .legenda { font-size: 10px; color: #444; margin-top: 3px; }
        .assinatura { margin-top: 50px; text-align: center; }
        .linha { display: inline-block; border-top: 1px solid #333; padding-top: 4px; min-width: 260px; }
    </style>
</head>
<body>
    @php
        $fuso = 'America/Sao_Paulo';
        $grupoAtual = false;
    @endphp
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => 'Relatório de vistoria', 'subtitulo' => $modelo['nome'] ?? '', 'usuario' => $fiscal])

    <h2>Relatório de vistoria nº {{ $v->id }}</h2>

    <table class="dados">
        <tr><th>Formulário</th><td>{{ $modelo['nome'] ?? '-' }}</td></tr>
        @if ($processo)<tr><th>Processo</th><td>{{ $processo->numero_protocolo }} — {{ $processo->assunto }}</td></tr>@endif
        <tr><th>Objeto fiscalizado</th><td>{{ $v->objeto ?: '-' }}</td></tr>
        <tr><th>Local</th><td>{{ $v->local_descricao ?: '-' }}</td></tr>
        <tr><th>Coordenadas</th><td>@if ($v->latitude !== null){{ $v->latitude }}, {{ $v->longitude }}@if ($v->precisao) (precisão ±{{ (int) $v->precisao }} m)@endif @else - @endif</td></tr>
        <tr><th>Realizada em</th><td>{{ \Illuminate\Support\Carbon::parse($v->realizada_em, 'UTC')->setTimezone($fuso)->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Fiscal</th><td>{{ $fiscal }}</td></tr>
        @if ($resultado)<tr><th>Resultado</th><td><b>{{ $resultado }}</b></td></tr>@endif
        <tr><th>Sincronizada em</th><td>{{ \Illuminate\Support\Carbon::parse($v->created_at, 'UTC')->setTimezone($fuso)->format('d/m/Y H:i') }}</td></tr>
    </table>

    @if (! empty($modelo['campos']))
        <h3>Dados</h3>
        <table class="dados">
            @foreach ($modelo['campos'] as $c)
                <tr><th>{{ $c['rotulo'] }}</th><td>@php $val = $campos[$c['chave']] ?? ''; @endphp
                    {{ $c['tipo'] === 'data' && $val ? \Illuminate\Support\Carbon::parse($val)->format('d/m/Y') : ($val !== '' ? $val : '-') }}</td></tr>
            @endforeach
        </table>
    @endif

    <h3>Checklist</h3>
    <table class="itens">
        @foreach ($modelo['itens'] ?? [] as $i)
            @if (($i['grupo'] ?? null) !== $grupoAtual && ($i['grupo'] ?? null))
                <tr class="grupo"><td colspan="2">{{ $i['grupo'] }}</td></tr>
            @endif
            @php $grupoAtual = $i['grupo'] ?? null; $r = $respostas[$i['chave']] ?? []; @endphp
            <tr>
                <td>{{ $i['texto'] }}@if (! empty($r['obs']))<br><span class="obs">{{ $r['obs'] }}</span>@endif</td>
                <td class="r {{ $r['resposta'] ?? '' }}">{{ $rotulos[$r['resposta'] ?? ''] ?? 'Sem resposta' }}</td>
            </tr>
        @endforeach
    </table>

    @if ($v->observacao)
        <h3>Observação final</h3>
        <p>{!! nl2br(e($v->observacao)) !!}</p>
    @endif

    @if ($fotos->isNotEmpty())
        <h3>Fotos ({{ $fotos->count() }})</h3>
        <table class="fotos">
            @foreach ($fotos->chunk(2) as $par)
                <tr>
                    @foreach ($par as $n => $f)
                        <td>@if ($f['data'])<img src="{{ $f['data'] }}">@endif
                            <p class="legenda">Foto {{ $n + 1 }}{{ $f['legenda'] ? ' — ' . $f['legenda'] : '' }}</p></td>
                    @endforeach
                    @if ($par->count() < 2)<td></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    <div class="assinatura"><p class="linha">{{ $fiscal }}<br>Fiscal responsável</p></div>
</body>
</html>
