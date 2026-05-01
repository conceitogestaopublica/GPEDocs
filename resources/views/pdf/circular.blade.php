<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Circular Interna - {{ $circular->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; line-height: 1.5; padding: 30px 40px; }
        .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; color: #1e40af; margin-bottom: 3px; }
        .header .numero { font-size: 14px; color: #555; margin-bottom: 3px; }
        .header p { font-size: 10px; color: #666; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e40af; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; color: #555; padding: 3px 10px 3px 0; width: 160px; font-size: 10px; }
        .info-value { display: table-cell; padding: 3px 0; font-size: 11px; }
        .conteudo { padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; line-height: 1.7; white-space: pre-wrap; word-wrap: break-word; }
        table.rastreio { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.rastreio th { background: #1e40af; color: white; padding: 6px 8px; font-size: 9px; text-align: left; text-transform: uppercase; }
        table.rastreio td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        table.rastreio tr:nth-child(even) { background: #f8fafc; }
        .status-lido { color: #16a34a; font-weight: bold; }
        .status-nao-lido { color: #ca8a04; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #1e40af; text-align: center; font-size: 9px; color: #999; }
        .qr-placeholder { text-align: center; margin-top: 15px; }
        .destinatarios-list { font-size: 11px; }
        .destinatarios-list span { display: inline; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null])

    {{-- Cabecalho --}}
    <div class="header">
        <h1>CIRCULAR INTERNA</h1>
        <div class="numero">{{ $circular->numero }}</div>
        <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    {{-- Identificacao --}}
    <div class="section">
        <div class="section-title">Identificacao da Circular</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">De (Remetente):</div>
                <div class="info-value">
                    {{ $circular->remetente?->name ?? '-' }}
                    @if($circular->setor_origem)
                        — {{ $circular->setor_origem }}
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Para (Destino):</div>
                <div class="info-value">
                    @if($circular->destino_tipo === 'todos')
                        Toda a Organizacao
                    @elseif($circular->destino_tipo === 'setores')
                        Setores: {{ implode(', ', $circular->destino_setores ?? []) }}
                    @else
                        <span class="destinatarios-list">
                            @foreach($circular->destinatarios as $i => $dest)
                                <span>{{ $dest->usuario?->name ?? '-' }}{{ !$loop->last ? ', ' : '' }}</span>
                            @endforeach
                        </span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Assunto:</div>
                <div class="info-value"><strong>{{ $circular->assunto }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data:</div>
                <div class="info-value">{{ $circular->created_at?->format('d/m/Y H:i:s') }}</div>
            </div>
        </div>
    </div>

    {{-- Conteudo --}}
    <div class="section">
        <div class="section-title">Conteudo</div>
        <div class="conteudo">{{ $circular->conteudo }}</div>
    </div>

    {{-- Rastreio de Leitura --}}
    <div class="section">
        <div class="section-title">Rastreio de Leitura</div>
        <table class="rastreio">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Destinatario</th>
                    <th>E-mail</th>
                    <th>Status</th>
                    <th>Data/Hora da Leitura</th>
                </tr>
            </thead>
            <tbody>
                @foreach($circular->destinatarios as $i => $dest)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $dest->usuario?->name ?? '-' }}</td>
                    <td>{{ $dest->usuario?->email ?? '-' }}</td>
                    <td class="{{ $dest->lido ? 'status-lido' : 'status-nao-lido' }}">
                        {{ $dest->lido ? 'Lido' : 'Nao lido' }}
                    </td>
                    <td>
                        @if($dest->lido && $dest->lido_em)
                            {{ \Carbon\Carbon::parse($dest->lido_em)->format('d/m/Y H:i:s') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Rodape --}}
    <div class="footer">
        @if($circular->qr_code_token)
        <div class="qr-placeholder">
            <p style="margin-bottom: 5px;"><strong>Verificacao:</strong> {{ config('app.url') }}/verificar/{{ $circular->qr_code_token }}</p>
        </div>
        @endif
        <p style="margin-top: 10px;"><strong>Conceito Gestao Publica</strong> — Plataforma Digital Integrada</p>
        <p>Documento gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
