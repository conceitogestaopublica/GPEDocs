{{--
    Cabeçalho e rodapé padrão dos relatórios da casa (o mesmo dos relatórios do gpe2): caixa com o brasão e os
    dados do município à esquerda, a unidade gestora e o nome do relatório à direita; no rodapé, "GPE Cloud",
    o usuário do sistema e "Página N de M" (este escrito por App\Support\Relatorio). Repete em todas as páginas (position: fixed nas margens da página).
    Incluir logo depois do <body>, com:
      ug        Ug (ou null)
      titulo    nome do relatório (ex.: "Ofício Eletrônico")
      subtitulo linha abaixo do nome (ex.: o número), opcional
      usuario   nome de quem gerou; sem ele, o usuário logado
      rodape    false quando o rodapé é carimbado depois (árvore do processo)
--}}
@php
    $brasaoSrc = null;
    if (! empty($ug?->brasao_path)) {
        try {
            $abs = \Illuminate\Support\Facades\Storage::disk('documentos')->path($ug->brasao_path);
            if (is_file($abs)) {
                $mime = mime_content_type($abs) ?: 'image/png';
                $brasaoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
            }
        } catch (\Throwable $e) {
            $brasaoSrc = null;
        }
    }

    // Endereço vem da cadeia logradouro → bairro → município (Ug::enderecoArray).
    $end = $ug ? $ug->enderecoArray() : [];
    $estados = ['AC' => 'ACRE', 'AL' => 'ALAGOAS', 'AP' => 'AMAPÁ', 'AM' => 'AMAZONAS', 'BA' => 'BAHIA', 'CE' => 'CEARÁ',
        'DF' => 'DISTRITO FEDERAL', 'ES' => 'ESPÍRITO SANTO', 'GO' => 'GOIÁS', 'MA' => 'MARANHÃO', 'MT' => 'MATO GROSSO',
        'MS' => 'MATO GROSSO DO SUL', 'MG' => 'MINAS GERAIS', 'PA' => 'PARÁ', 'PB' => 'PARAÍBA', 'PR' => 'PARANÁ',
        'PE' => 'PERNAMBUCO', 'PI' => 'PIAUÍ', 'RJ' => 'RIO DE JANEIRO', 'RN' => 'RIO GRANDE DO NORTE',
        'RS' => 'RIO GRANDE DO SUL', 'RO' => 'RONDÔNIA', 'RR' => 'RORAIMA', 'SC' => 'SANTA CATARINA', 'SP' => 'SÃO PAULO',
        'SE' => 'SERGIPE', 'TO' => 'TOCANTINS'];
    $uf = mb_strtoupper((string) ($end['uf'] ?? ''));
    $municipio = ($end['cidade'] ?? null) ? 'MUNICÍPIO DE ' . mb_strtoupper($end['cidade']) : mb_strtoupper($ug?->nome ?? '');
    $estado = $uf !== '' ? 'ESTADO DE ' . ($estados[$uf] ?? $uf) : '';

    $cep = preg_replace('/\D/', '', (string) ($end['cep'] ?? ''));
    $cep = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
    $rua = trim(implode(', ', array_filter([
        $end['logradouro'] ?? null, $end['numero'] ?? null, $end['complemento'] ?? null, $end['bairro'] ?? null,
    ])));
    $endereco = trim(implode(' - ', array_filter([$rua, $cep !== '' ? "CEP: {$cep}" : null])));
    $documentos = trim(implode('   ', array_filter([
        $ug?->cnpj ? "CNPJ: {$ug->cnpj}" : null,
        $ug?->telefone ? "Tel: {$ug->telefone}" : null,
    ])));

    $usuarioDoSistema = $usuario ?? auth()->user()?->name ?? 'Sistema';
    $comRodape = $rodape ?? true;
@endphp
<style>
    @page { margin: 37mm 12mm {{ $comRodape ? '16mm' : '14mm' }} 12mm !important; }
    body { padding: 0 !important; margin: 0 !important; }
    .rel-cab { position: fixed; top: -31mm; left: 0; right: 0; height: 26mm; }
    .rel-cab table { width: 100%; height: 26mm; border-collapse: collapse; border: 1px solid #333; font-family: 'Helvetica', 'Arial', sans-serif; color: #000; }
    .rel-cab td { vertical-align: middle; padding: 2mm 3mm; }
    .rel-cab td.esq { width: 60%; border-right: 1px solid #333; }
    .rel-cab .brasao { width: 20mm; padding-right: 0; text-align: center; }
    .rel-cab .brasao img { max-width: 19mm; max-height: 21mm; }
    .rel-cab .mun { font-size: 13px; font-weight: bold; line-height: 1.25; }
    .rel-cab .uf { font-size: 9px; font-weight: bold; line-height: 1.3; }
    .rel-cab .linha { font-size: 8.5px; line-height: 1.35; }
    .rel-cab .gestora { font-size: 11px; font-weight: bold; text-align: center; line-height: 1.3; }
    .rel-cab .nome { font-size: 11px; text-align: center; margin-top: 1.5mm; line-height: 1.3; }
    .rel-cab .sub { font-size: 9px; text-align: center; color: #333; margin-top: 1mm; }
    .rel-rod { position: fixed; bottom: -11mm; left: 0; right: 0; height: 8mm; border-top: 1px solid #333; padding-top: 1mm; font-family: 'Helvetica', 'Arial', sans-serif; font-size: 8px; color: #000; }
    .rel-rod td { vertical-align: top; padding: 0; }
</style>

<div class="rel-cab">
    <table>
        <tr>
            <td class="esq">
                <table style="height: auto; border: 0;">
                    <tr>
                        @if($brasaoSrc)
                            <td class="brasao" style="padding: 0 2mm 0 0;"><img src="{{ $brasaoSrc }}" alt="Brasão"></td>
                        @endif
                        <td style="padding: 0;">
                            <div class="mun">{{ $municipio }}</div>
                            @if($estado)<div class="uf">{{ $estado }}</div>@endif
                            @if($documentos)<div class="linha">{{ $documentos }}</div>@endif
                            @if($endereco)<div class="linha">{{ $endereco }}</div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="gestora">{{ mb_strtoupper($ug?->nome ?? '') }}</div>
                <div class="nome">{{ $titulo ?? '' }}</div>
                @if(! empty($subtitulo))<div class="sub">{{ $subtitulo }}</div>@endif
            </td>
        </tr>
    </table>
</div>

@if($comRodape)
    <div class="rel-rod">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td>GPE Cloud — Gestão Pública Eficiente</td>
                <td style="text-align: right;">Usuário do Sistema: {{ $usuarioDoSistema }}</td>
            </tr>
        </table>
    </div>
@endif
