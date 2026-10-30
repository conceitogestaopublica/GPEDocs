<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ofício Eletrônico - {{ $oficio->numero }}</title>
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
        .conteudo-html { white-space: normal; }
        .conteudo-html p { margin: 0 0 8px; }
        .conteudo-html ul, .conteudo-html ol { margin: 0 0 8px 20px; }
        .conteudo-html h1, .conteudo-html h2, .conteudo-html h3 { margin: 8px 0 6px; color: #333; }
        .conteudo-html h1 { font-size: 15px; } .conteudo-html h2 { font-size: 13px; } .conteudo-html h3 { font-size: 12px; }
        .conteudo-html blockquote { border-left: 3px solid #cbd5e1; padding-left: 8px; color: #555; }
        .conteudo-html hr { border: 0; border-top: 1px solid #cbd5e1; margin: 8px 0; }
        .resposta { padding: 10px 15px; margin-bottom: 10px; background: #f1f5f9; border-left: 3px solid #1e40af; border-radius: 0 4px 4px 0; }
        .resposta.externo { border-left-color: #7c3aed; background: #f5f3ff; }
        .resposta-header { font-size: 10px; color: #555; margin-bottom: 4px; }
        .resposta-header strong { color: #1e40af; }
        .resposta.externo .resposta-header strong { color: #7c3aed; }
        .resposta-body { font-size: 11px; white-space: pre-wrap; word-wrap: break-word; }
        .badge-externo { display: inline-block; background: #f5f3ff; color: #7c3aed; font-size: 8px; font-weight: bold; padding: 1px 6px; border-radius: 10px; border: 1px solid #c4b5fd; margin-left: 4px; }
        table.rastreio { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.rastreio th { background: #1e40af; color: white; padding: 6px 8px; font-size: 9px; text-align: left; text-transform: uppercase; }
        table.rastreio td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        table.rastreio tr:nth-child(even) { background: #f8fafc; }
        .status-sim { color: #16a34a; font-weight: bold; }
        .status-nao { color: #ca8a04; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #1e40af; text-align: center; font-size: 9px; color: #999; }
        .qr-placeholder { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null])

    {{-- Cabecalho --}}
    <div class="header">
        <h1>OFÍCIO ELETRÔNICO</h1>
        <div class="numero">{{ $oficio->numero }}</div>
        <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    {{-- Identificacao --}}
    <div class="section">
        <div class="section-title">Identificação do Ofício</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">De (Remetente):</div>
                <div class="info-value">
                    {{ $oficio->remetente?->name ?? '-' }}
                    @if($oficio->setor_origem)
                        — {{ $oficio->setor_origem }}
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Para (Destinatário):</div>
                <div class="info-value">
                    {{ $oficio->destinatario_nome }}
                    @if($oficio->destinatario_cargo)
                        — {{ $oficio->destinatario_cargo }}
                    @endif
                </div>
            </div>
            @if($oficio->destinatario_orgao)
            <div class="info-row">
                <div class="info-label">Órgão/Instituição:</div>
                <div class="info-value">{{ $oficio->destinatario_orgao }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">E-mail:</div>
                <div class="info-value">{{ $oficio->destinatario_email }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Assunto:</div>
                <div class="info-value"><strong>{{ $oficio->assunto }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data:</div>
                <div class="info-value">{{ $oficio->created_at?->format('d/m/Y H:i:s') }}</div>
            </div>
        </div>
    </div>

    {{-- Conteudo --}}
    <div class="section">
        <div class="section-title">Conteúdo</div>
        {{-- HTML do editor, já limpo e com as variáveis trocadas (Oficio::conteudoFinal). --}}
        <div class="conteudo conteudo-html">{!! $oficio->conteudoFinal() !!}</div>
    </div>

    {{-- Respostas --}}
    @if($oficio->respostas && $oficio->respostas->count() > 0)
    <div class="section">
        <div class="section-title">Respostas ({{ $oficio->respostas->count() }})</div>
        @foreach($oficio->respostas as $resposta)
        <div class="resposta {{ $resposta->externo ? 'externo' : '' }}">
            <div class="resposta-header">
                <strong>{{ $resposta->externo ? $resposta->respondente_nome : ($resposta->usuario?->name ?? $resposta->respondente_nome ?? 'Usuario') }}</strong>
                @if($resposta->externo)
                    <span class="badge-externo">EXTERNO</span>
                @endif
                — {{ $resposta->created_at?->format('d/m/Y H:i:s') }}
            </div>
            <div class="resposta-body">{{ $resposta->conteudo }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Rastreio de Entrega --}}
    <div class="section">
        <div class="section-title">Rastreio de Entrega</div>
        <table class="rastreio">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Status</th>
                    <th>Data/Hora</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Enviado</td>
                    <td class="status-sim">Sim</td>
                    <td>{{ $oficio->enviado_em?->format('d/m/Y H:i:s') ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Entregue</td>
                    <td class="{{ $oficio->entregue_em ? 'status-sim' : 'status-nao' }}">
                        {{ $oficio->entregue_em ? 'Sim' : 'Aguardando' }}
                    </td>
                    <td>{{ $oficio->entregue_em?->format('d/m/Y H:i:s') ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Lido pelo Destinatário</td>
                    <td class="{{ $oficio->lido_em ? 'status-sim' : 'status-nao' }}">
                        {{ $oficio->lido_em ? 'Sim' : 'Aguardando' }}
                    </td>
                    <td>{{ $oficio->lido_em?->format('d/m/Y H:i:s') ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Rodape --}}
    <div class="footer">
        @if($oficio->qr_code_token)
        <div class="qr-placeholder">
            <p style="margin-bottom: 5px;"><strong>Verificação:</strong> {{ $qrCodeUrl }}</p>
        </div>
        @endif
        <p style="margin-top: 10px;"><strong>Conceito Gestão Pública</strong> — Plataforma Digital Integrada</p>
        <p>Documento gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
