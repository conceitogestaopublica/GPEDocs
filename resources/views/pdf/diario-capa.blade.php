<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diário Oficial — Edição {{ $e->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.45; padding: 30px 40px; }
        .titulo { text-align: center; margin: 26px 0 4px; font-size: 24px; font-weight: bold; color: #0e7490; letter-spacing: 1px; }
        .sub { text-align: center; font-size: 12px; color: #444; margin-bottom: 4px; }
        .faixa { margin: 14px 0 18px; padding: 8px 12px; background: #f1f5f9; border-top: 3px solid #0e7490; text-align: center; font-size: 12px; }
        h3 { font-size: 13px; color: #0e7490; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin: 10px 0 6px; }
        .grupo { margin: 10px 0 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #555; }
        table.sumario { width: 100%; border-collapse: collapse; }
        table.sumario td { padding: 3px 0; vertical-align: top; border-bottom: 1px dotted #ccc; }
        table.sumario td.pag { width: 50px; text-align: right; font-weight: bold; }
        .ementa { font-size: 9px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Diário Oficial Eletrônico', 'subtitulo' => 'Edição nº ' . $e->numero, 'usuario' => 'Diário Oficial'])

    <p class="titulo">DIÁRIO OFICIAL ELETRÔNICO</p>
    <p class="sub">{{ $ug?->nome }}</p>
    <div class="faixa">
        Edição nº <b>{{ $e->numero }}</b> · {{ \App\Models\Diario\Edicao::TIPOS[$e->tipo] ?? $e->tipo }} ·
        {{ \App\Support\Variaveis::dataPorExtenso($e->data->copy()->setTime(12, 0)) }}
    </div>

    <h3>Sumário</h3>
    @php $grupo = null; @endphp
    <table class="sumario">
        @foreach($sumario as $s)
            @if($s['grupo'] !== $grupo)
                <tr><td colspan="2"><p class="grupo">{{ $s['grupo'] }}</p></td></tr>
                @php $grupo = $s['grupo']; @endphp
            @endif
            <tr>
                <td><b>{{ $s['ato'] }}</b><br><span class="ementa">{{ $s['ementa'] }}</span></td>
                <td class="pag">{{ $s['pagina'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
