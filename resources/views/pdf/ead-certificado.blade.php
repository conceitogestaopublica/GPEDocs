<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado — {{ $curso->titulo }}</title>
    <style>
        @page { margin: 26px 34px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; }
        .moldura { border: 3px double #0e7490; padding: 40px 50px; height: 700px; position: relative; }
        .orgao { text-align: center; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 1px; }
        h1 { text-align: center; font-size: 40px; color: #0e7490; margin: 40px 0 10px; letter-spacing: 4px; }
        .texto { text-align: center; font-size: 17px; line-height: 1.8; margin: 26px 50px 0; }
        .nome { font-size: 22px; font-weight: bold; }
        .conteudo { margin-top: 40px; font-size: 11.5px; color: #374151; }
        .conteudo b { color: #0e7490; }
        .conteudo ol { margin: 4px 0 0 18px; }
        .rodape { position: absolute; left: 40px; right: 40px; bottom: 22px; font-size: 9.5px; color: #475569; }
        .qr { position: absolute; right: 40px; bottom: 18px; text-align: center; font-size: 8px; color: #64748b; }
        .qr img { width: 86px; height: 86px; }
    </style>
</head>
<body>
    @php
        $qrPng = (new \TCPDF2DBarcode($url, 'QRCODE,M'))->getBarcodePngData(4, 4, [0, 0, 0]);
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $data = $concluido->format('j') . ' de ' . $meses[$concluido->month - 1] . ' de ' . $concluido->format('Y');
        $horas = rtrim(rtrim(number_format((float) $curso->carga_horaria, 1, ',', ''), '0'), ',');
    @endphp
    <div class="moldura">
        <p class="orgao">{{ $ug?->nome ?? 'Administração Municipal' }} · Treinamento a distância</p>
        <h1>CERTIFICADO</h1>
        <p class="texto">Certificamos que <span class="nome">{{ $servidor }}</span>@if($cpf), CPF {{ $cpf }},@endif
            concluiu o curso <b>{{ $curso->titulo }}</b>, com carga horária de <b>{{ $horas }} hora(s)</b>,
            obtendo nota <b>{{ number_format((float) $m->nota, 1, ',', '') }}</b> na avaliação final, em {{ $data }}.</p>

        <div class="conteudo">
            <b>Conteúdo programático</b>
            <ol>
                @foreach ($aulas as $a)<li>{{ $a }}</li>@endforeach
            </ol>
        </div>

        <p class="rodape">Certificado emitido pelo sistema GPE Docs. Código de verificação: <b>{{ $m->certificado_codigo }}</b><br>
            Confira a autenticidade em {{ $url }}</p>
        <div class="qr"><img src="data:image/png;base64,{{ base64_encode($qrPng) }}" alt="QR"><br>verificar</div>
    </div>
</body>
</html>
