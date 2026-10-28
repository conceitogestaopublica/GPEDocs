{{-- Cabecalho oficial da Unidade Gestora — usado em todos os PDFs (memorandos, oficios, circulares, decisoes de processo). --}}
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

    // Endereço vem da cadeia logradouro → bairro → município (Ug::enderecoArray). Ler $ug->logradouro
    // direto imprimia o objeto da relação em JSON no cabeçalho.
    $end = $ug ? $ug->enderecoArray() : [];
    $endereco = trim(implode(', ', array_filter([
        ($end['logradouro'] ?? null) ? $end['logradouro'] . (($end['numero'] ?? null) ? ", {$end['numero']}" : '') : null,
        $end['complemento'] ?? null,
        $end['bairro'] ?? null,
        (($end['cidade'] ?? null) && ($end['uf'] ?? null)) ? "{$end['cidade']} - {$end['uf']}" : ($end['cidade'] ?? $end['uf'] ?? null),
        ($end['cep'] ?? null) ? "CEP {$end['cep']}" : null,
    ])), ', ');

    $contato = trim(implode(' - ', array_filter([
        $ug?->telefone,
        $ug?->site,
        $ug?->email_institucional,
    ])), ' - ');
@endphp

<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
        @if($brasaoSrc)
            <td style="width: 70px; vertical-align: middle; padding-right: 10px;">
                <img src="{{ $brasaoSrc }}" alt="Brasao" style="width: 60px; height: auto;" />
            </td>
        @endif
        <td style="vertical-align: middle; text-align: center;">
            <div style="font-size: 13px; font-weight: bold; color: #1e293b;">
                {{ strtoupper($ug?->nome ?? 'UNIDADE GESTORA') }}
            </div>
            @if($endereco)
                <div style="font-size: 9px; color: #475569; margin-top: 2px;">{{ $endereco }}</div>
            @endif
            @if($contato)
                <div style="font-size: 9px; color: #475569;">{{ $contato }}</div>
            @endif
            @if($ug?->cnpj)
                <div style="font-size: 9px; color: #475569;">CNPJ: {{ $ug->cnpj }}</div>
            @endif
        </td>
    </tr>
</table>
<hr style="border: 0; border-top: 2px solid #1e40af; margin-bottom: 14px;" />
