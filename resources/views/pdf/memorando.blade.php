<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Memorando Interno - {{ $memorando->numero }}</title>
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
        .badge-confidencial { display: inline-block; background: #fef2f2; color: #dc2626; font-size: 9px; font-weight: bold; padding: 2px 8px; border-radius: 10px; border: 1px solid #fca5a5; }
        .conteudo { padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; line-height: 1.7; white-space: pre-wrap; word-wrap: break-word; }
        .resposta { padding: 10px 15px; margin-bottom: 10px; background: #f1f5f9; border-left: 3px solid #1e40af; border-radius: 0 4px 4px 0; }
        .resposta-header { font-size: 10px; color: #555; margin-bottom: 4px; }
        .resposta-header strong { color: #1e40af; }
        .resposta-body { font-size: 11px; white-space: pre-wrap; word-wrap: break-word; }
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
        <h1>MEMORANDO INTERNO</h1>
        <div class="numero">{{ $memorando->numero }}</div>
        <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    {{-- Folha de Rosto --}}
    <div class="section">
        <div class="section-title">Identificacao do Memorando</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">De (Remetente):</div>
                <div class="info-value">
                    {{ $memorando->remetente?->name ?? '-' }}
                    @if($memorando->setor_origem)
                        — {{ $memorando->setor_origem }}
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Para (Destinatarios):</div>
                <div class="info-value destinatarios-list">
                    @foreach($memorando->destinatarios as $i => $dest)
                        <span>{{ $dest->user?->name ?? $dest->name ?? '-' }}{{ !$loop->last ? ', ' : '' }}</span>
                    @endforeach
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Assunto:</div>
                <div class="info-value"><strong>{{ $memorando->assunto }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data:</div>
                <div class="info-value">{{ $memorando->created_at?->format('d/m/Y H:i:s') }}</div>
            </div>
            @if($memorando->confidencial)
            <div class="info-row">
                <div class="info-label">Classificacao:</div>
                <div class="info-value"><span class="badge-confidencial">CONFIDENCIAL</span></div>
            </div>
            @endif
        </div>
    </div>

    {{-- Conteudo --}}
    <div class="section">
        <div class="section-title">Conteudo</div>
        <div class="conteudo">{{ $memorando->conteudo }}</div>
    </div>

    {{-- Respostas --}}
    @if($memorando->respostas && $memorando->respostas->count() > 0)
    <div class="section">
        <div class="section-title">Respostas ({{ $memorando->respostas->count() }})</div>
        @foreach($memorando->respostas as $resposta)
        <div class="resposta">
            <div class="resposta-header">
                <strong>{{ $resposta->user?->name ?? 'Usuario' }}</strong>
                — {{ $resposta->created_at?->format('d/m/Y H:i:s') }}
            </div>
            <div class="resposta-body">{{ $resposta->conteudo }}</div>
        </div>
        @endforeach
    </div>
    @endif

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
                @foreach($memorando->destinatarios as $i => $dest)
                @php
                    $lido = $dest->pivot->lido ?? false;
                    $lidoEm = $dest->pivot->lido_em ?? null;
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $dest->user?->name ?? $dest->name ?? '-' }}</td>
                    <td>{{ $dest->user?->email ?? $dest->email ?? '-' }}</td>
                    <td class="{{ $lido ? 'status-lido' : 'status-nao-lido' }}">
                        {{ $lido ? 'Lido' : 'Nao lido' }}
                    </td>
                    <td>
                        @if($lido && $lidoEm)
                            {{ \Carbon\Carbon::parse($lidoEm)->format('d/m/Y H:i:s') }}
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
        @if($memorando->qr_code_token)
        <div class="qr-placeholder">
            <p style="margin-bottom: 5px;"><strong>Verificacao:</strong> {{ config('app.url') }}/verificar/{{ $memorando->qr_code_token }}</p>
        </div>
        @endif
        <p style="margin-top: 10px;"><strong>Conceito Gestao Publica</strong> — Plataforma Digital Integrada</p>
        <p>Documento gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
