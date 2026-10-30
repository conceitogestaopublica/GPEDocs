{{-- Árvore do processo (TR 4.38.27): capa + sumário, ou um trecho de atos em sequência --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5px; color: #222; line-height: 1.45; }
    h1 { font-size: 20px; color: #0e5a7a; margin: 0 0 4px; }
    h2 { font-size: 13px; color: #0e5a7a; border-bottom: 1px solid #cfe3ea; padding-bottom: 3px; margin: 0 0 8px; }
    .ato { margin-bottom: 16px; page-break-inside: avoid; }
    .meta { color: #666; font-size: 9.5px; margin-bottom: 6px; }
    .caixa { background: #f6f9fb; border: 1px solid #e3edf2; border-radius: 4px; padding: 8px 10px; white-space: pre-wrap; word-wrap: break-word; }
    .nota { background: #fff8e6; border-color: #f3e2b3; }
    table.dados { width: 100%; border-collapse: collapse; }
    table.dados td { padding: 3px 6px 3px 0; vertical-align: top; border-bottom: 1px solid #eef3f5; }
    table.dados td.r { color: #555; width: 35%; }
    .selo { display: inline-block; font-size: 9px; font-weight: bold; padding: 1px 6px; border-radius: 8px; }
    .deferido { background: #e5f6ea; color: #17733a; } .indeferido { background: #fde8e8; color: #9b1c1c; }
    .capa { text-align: center; padding-top: 40px; }
    .capa .num { font-size: 26px; font-weight: bold; color: #0e5a7a; margin: 18px 0 6px; }
    .sumario { margin-top: 36px; text-align: left; }
    .sumario td { padding: 3px 0; border-bottom: 1px dotted #ccc; }
    .sumario td.pg { text-align: right; width: 50px; }
    .ref { border: 2px dashed #b9c9d1; border-radius: 6px; padding: 24px; text-align: center; margin-top: 60px; }
    img.anexo { max-width: 100%; max-height: 225mm; }
    @include('pdf.partials.estilo-texto-rico')
</style>
</head>
<body>
{{-- Rodapé e "Fl. N/M" são carimbados por ArvoreDoProcesso::numerar, com a numeração da árvore inteira --}}
@include('pdf.partials.cabecalho-ug', ['ug' => $ugCabecalho, 'titulo' => 'Árvore do Processo',
    'subtitulo' => $protocolo, 'usuario' => $usuario, 'rodape' => false])
@if ($modo === 'capa')
    <div class="capa">
        <h1>Árvore do processo</h1>
        <div class="num">{{ $processo->numero_protocolo }}</div>
        <p style="font-size:13px">{{ $processo->assunto }}</p>
        <p class="meta">{{ $processo->tipoProcesso?->nome }} · aberto em {{ $fmt($processo->created_at) }}</p>
        <p class="meta">{{ $configuracao }} · gerada em {{ $fmt(now()) }} por {{ $solicitante }}</p>
        <table class="sumario" style="width:100%">
            <tr><td colspan="2"><b>Sumário</b></td></tr>
            @foreach ($sumario as $item)
                <tr><td>{{ $item['titulo'] }}</td><td class="pg">fl. {{ $item['pagina'] }}</td></tr>
            @endforeach
        </table>
    </div>
@elseif ($modo === 'referencia')
    <div class="ref">
        <h2 style="border:0">Anexo não convertível em PDF</h2>
        <p><b>{{ $anexo->nome }}</b></p>
        <p class="meta">{{ $anexo->mime_type }} · {{ number_format(($anexo->tamanho ?? 0) / 1024, 1, ',', '.') }} KB · juntado em {{ $fmt($anexo->created_at) }}</p>
        <p class="meta">SHA-256: {{ $anexo->hash_sha256 }}</p>
        <p>O arquivo original está no processo, no sistema, e pode ser baixado por quem tem acesso.</p>
    </div>
@elseif ($modo === 'imagem')
    <h2>{{ $titulo }}</h2>
    <p class="meta">Juntado em {{ $fmt($anexo->created_at) }}</p>
    <img class="anexo" src="{{ $src }}" alt="">
@else
    @foreach ($atos as $ato)
        <div class="ato">
            <h2>{{ $ato['titulo'] }}</h2>
            @if (! empty($ato['meta']))<div class="meta">{{ $ato['meta'] }}</div>@endif
            @if (! empty($ato['selo']))<span class="selo {{ $ato['selo'] }}">{{ $ato['selo'] === 'deferido' ? 'Setor deferiu' : 'Setor indeferiu' }}</span>@endif
            @if (! empty($ato['dados']))
                <table class="dados">
                    @foreach ($ato['dados'] as $rotulo => $valor)
                        <tr><td class="r">{{ $rotulo }}</td><td>{{ $valor }}</td></tr>
                    @endforeach
                </table>
            @endif
            @if (! empty($ato['texto']))<div class="caixa {{ ! empty($ato['interno']) ? 'nota' : '' }}">{{ $ato['texto'] }}</div>@endif
            {{-- Despacho do editor: HTML já limpo (HtmlSeguro) --}}
            @if (! empty($ato['html']))<div class="caixa conteudo-html">{!! $ato['html'] !!}</div>@endif
        </div>
    @endforeach
@endif
</body>
</html>
