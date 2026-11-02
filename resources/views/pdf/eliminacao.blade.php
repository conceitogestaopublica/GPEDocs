<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} {{ $l->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.5; padding: 30px 40px; }
        h2 { text-align: center; font-size: 15px; margin: 20px 0 4px; text-transform: uppercase; }
        .codigo { text-align: center; font-size: 12px; color: #0e7490; font-weight: bold; margin-bottom: 14px; }
        p.texto { text-align: justify; margin-bottom: 10px; }
        table.itens { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
        table.itens th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9.5px; text-align: left; color: #475569; }
        table.itens td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9.5px; vertical-align: top; }
        .assinaturas { margin-top: 50px; width: 100%; }
        .assinaturas td { width: 50%; text-align: center; padding: 0 20px; font-size: 10px; }
        .linha { border-top: 1px solid #333; padding-top: 4px; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => $titulo, 'subtitulo' => $l->numero, 'usuario' => $usuario])

    <h2>{{ $titulo }}</h2>
    <p class="codigo">{{ $l->numero }}</p>

    @php
        $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
        $hora = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y') : '-';
        $inicial = $itens->min('data_inicial');
        $final = $itens->max('data_final');
    @endphp

    @if ($documento === 'edital')
        <p class="texto">
            {{ $ug?->nome }}, em cumprimento à Resolução nº 40, de 9 de dezembro de 2014, do Conselho Nacional de Arquivos (CONARQ),
            e de acordo com a Listagem de Eliminação de Documentos {{ $l->numero }}, aprovada em {{ $hora($l->aprovada_em) }},
            faz saber a quem possa interessar que, a partir do {{ $l->edital_prazo_dias }}º dia subsequente à data de publicação deste
            Edital, se não houver oposição, eliminará os documentos relacionados abaixo, relativos ao período de {{ $fmt($inicial) }}
            a {{ $fmt($final) }}, cujos prazos de guarda previstos na tabela de temporalidade e destinação de documentos foram cumpridos.
        </p>
        <p class="texto">
            Os interessados poderão, no prazo citado, requerer às suas expensas o desentranhamento de documentos ou cópias de peças
            de processos, mediante petição dirigida ao órgão, desde que tenham respectiva qualificação e demonstração de legitimidade.
        </p>
        <p class="texto">Publicado em {{ $hora($l->edital_em) }}. Prazo para manifestações: {{ $l->edital_prazo_dias }} dias.</p>
    @elseif ($documento === 'termo')
        <p class="texto">
            Aos {{ $hora($l->eliminada_em) }}, o(a) {{ $ug?->nome }}, de acordo com o que consta da Listagem de Eliminação de
            Documentos {{ $l->numero }}, aprovada em {{ $hora($l->aprovada_em) }}, e do respectivo Edital de Ciência de Eliminação
            publicado em {{ $hora($l->edital_em) }}, procedeu à eliminação de {{ $itens->count() }} item(ns) documental(is), relativos
            ao período de {{ $fmt($inicial) }} a {{ $fmt($final) }}, cujos arquivos digitais foram apagados do repositório
            ({{ $l->arquivos_apagados }} arquivo(s)), preservados os metadados, a classificação e o histórico de cada item,
            na forma da Resolução CONARQ nº 40/2014.
        </p>
    @else
        <p class="texto">
            Relação dos documentos que cumpriram os prazos de guarda da tabela de temporalidade e destinação, com destinação final
            de eliminação, submetida à aprovação na forma da Resolução CONARQ nº 40/2014.
            Situação: <b>{{ \App\Support\Temporalidade::STATUS[$l->status] ?? $l->status }}</b>.
            @if ($l->observacao) Observação: {{ $l->observacao }} @endif
        </p>
    @endif

    <table class="itens">
        <thead>
            <tr><th>Código</th><th>Classificação</th><th>Tipo</th><th>Identificação</th><th>Assunto</th><th>Datas-limite</th></tr>
        </thead>
        <tbody>
            @foreach ($itens as $i)
                <tr>
                    <td>{{ $i->codigo }}</td>
                    <td>{{ $i->classificacao }}</td>
                    <td>{{ \App\Support\Temporalidade::ROTULOS[$i->tipo] ?? $i->tipo }}</td>
                    <td>{{ $i->identificacao }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $i->assunto, 120) }}</td>
                    <td>{{ $fmt($i->data_inicial) }} a {{ $fmt($i->data_final) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="texto">Total: {{ $itens->count() }} item(ns). Datas-limite do conjunto: {{ $fmt($inicial) }} a {{ $fmt($final) }}.</p>

    <table class="assinaturas">
        <tr>
            <td><p class="linha">{{ $criador }}<br>Responsável pela seleção</p></td>
            <td><p class="linha">{{ $aprovador ?? '________________' }}<br>{{ $documento === 'termo' ? 'Responsável pela eliminação' : 'Aprovação' }}</p></td>
        </tr>
    </table>
</body>
</html>
