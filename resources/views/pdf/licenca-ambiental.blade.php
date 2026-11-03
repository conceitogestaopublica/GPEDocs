<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $modalidade }} {{ $l->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.5; padding: 30px 40px; }
        h2 { text-align: center; font-size: 16px; margin: 18px 0 2px; text-transform: uppercase; }
        .numero { text-align: center; font-size: 13px; color: #047857; font-weight: bold; margin-bottom: 14px; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.dados th { width: 28%; text-align: left; vertical-align: top; background: #f0fdf4; padding: 6px 9px; border: 1px solid #bbf7d0; font-size: 10px; color: #166534; }
        table.dados td { padding: 6px 9px; border: 1px solid #bbf7d0; }
        h3 { font-size: 12px; margin: 12px 0 6px; color: #166534; }
        ol { margin-left: 18px; }
        ol li { margin-bottom: 4px; text-align: justify; }
        .rodape { margin-top: 24px; width: 100%; }
        .rodape td { vertical-align: bottom; font-size: 9.5px; color: #475569; }
        .qr img { width: 90px; height: 90px; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug, 'titulo' => $modalidade, 'subtitulo' => $l->numero, 'usuario' => $emissor->name])

    @php
        $qrPng = (new \TCPDF2DBarcode($url, 'QRCODE,M'))->getBarcodePngData(4, 4, [0, 0, 0]);
        $data = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
    @endphp

    <h2>{{ $modalidade }}</h2>
    <p class="numero">{{ $l->numero }}</p>

    <table class="dados">
        <tr><th>Empreendedor</th><td>{{ $l->empreendedor_nome }}@if($l->empreendedor_documento) — {{ \App\Support\DocumentoPessoa::mascarar($l->empreendedor_documento) }}@endif</td></tr>
        <tr><th>Empreendimento</th><td>{{ $l->empreendimento_nome ?: '-' }}</td></tr>
        <tr><th>Endereço</th><td>{{ $l->empreendimento_endereco ?: '-' }}</td></tr>
        <tr><th>Atividade</th><td>{{ $atividade?->codigo }} — {{ $atividade?->descricao }}</td></tr>
        <tr><th>Enquadramento</th><td>Porte {{ $portes[$l->porte] }} ({{ rtrim(rtrim(number_format((float) $l->porte_valor, 2, ',', '.'), '0'), ',') }} {{ $atividade?->unidade }}), potencial poluidor {{ $potenciais[$l->potencial] }} — <b>classe {{ $l->classe }}</b></td></tr>
        <tr><th>Processo</th><td>{{ $p->numero_protocolo }}</td></tr>
        <tr><th>Emissão</th><td>{{ \Illuminate\Support\Carbon::parse($l->emitida_em, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}</td></tr>
        <tr><th>Validade</th><td><b>até {{ $data($l->valida_ate) }}</b>@if($l->modalidade === 'LO' || $l->modalidade === 'RENOVACAO' || $l->modalidade === 'LAS' || $l->modalidade === 'LOC') — a renovação deve ser requerida com antecedência mínima de 120 dias do vencimento @endif</td></tr>
    </table>

    <h3>Condicionantes</h3>
    @if ($condicionantes->isEmpty())
        <p>Sem condicionantes específicas, observada a legislação ambiental aplicável.</p>
    @else
        <ol>
            @foreach ($condicionantes as $c)
                <li>{{ $c->descricao }}@if($c->prazo) <b>(prazo: {{ $data($c->prazo) }})</b>@endif</li>
            @endforeach
        </ol>
    @endif

    <p style="margin-top: 12px; text-align: justify;">
        A presente licença é concedida com base nas informações e documentos apresentados pelo empreendedor e não dispensa
        nem substitui outros alvarás, licenças ou autorizações exigidos pela legislação. O descumprimento das condicionantes
        ou a omissão de informações relevantes sujeita o empreendedor à suspensão ou ao cancelamento da licença
        (Resolução CONAMA nº 237/1997, art. 19).
    </p>

    <table class="rodape">
        <tr>
            <td>Emitida por {{ $emissor->name }}. Documento assinado digitalmente no GPE Docs.<br>
                Confira a autenticidade e a situação em {{ $url }}</td>
            <td class="qr" style="text-align: right;"><img src="data:image/png;base64,{{ base64_encode($qrPng) }}" alt="QR"></td>
        </tr>
    </table>
</body>
</html>
