<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Decisao - {{ $processo->numero_protocolo }}</title>
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

        .decisao-box { padding: 14px 16px; margin: 12px 0; border-radius: 6px; border: 2px solid; text-align: center; }
        .decisao-deferido   { background: #f0fdf4; border-color: #22c55e; color: #15803d; }
        .decisao-indeferido { background: #fef2f2; border-color: #ef4444; color: #b91c1c; }
        .decisao-parcial    { background: #fffbeb; border-color: #f59e0b; color: #b45309; }
        .decisao-titulo { font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .decisao-sub { font-size: 10px; color: #666; }

        .parecer { padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; line-height: 1.7; white-space: pre-wrap; word-wrap: break-word; }

        table.tramitacoes { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.tramitacoes th { background: #1e40af; color: white; padding: 6px 8px; font-size: 9px; text-align: left; text-transform: uppercase; }
        table.tramitacoes td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        table.tramitacoes tr:nth-child(even) { background: #f8fafc; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #1e40af; text-align: center; font-size: 9px; color: #999; }
        .lei { background: #eef2ff; padding: 10px; border-left: 3px solid #6366f1; font-size: 10px; color: #4338ca; margin-top: 16px; }
        .assinatura-area { margin-top: 30px; padding-top: 20px; border-top: 1px dashed #999; text-align: center; }
        .assinatura-area p { font-size: 10px; color: #888; margin-bottom: 50px; }
        .assinatura-line { border-bottom: 1px solid #333; width: 60%; margin: 0 auto; }
        .assinatura-nome { margin-top: 6px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug])

    <div class="header">
        <h1>DECISAO ADMINISTRATIVA</h1>
        <div class="numero">Processo {{ $processo->numero_protocolo }}</div>
        <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Identificacao do Processo</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Numero:</div>
                <div class="info-value">{{ $processo->numero_protocolo }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo:</div>
                <div class="info-value">{{ $processo->tipoProcesso?->nome ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Assunto:</div>
                <div class="info-value">{{ $processo->assunto }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Aberto por:</div>
                <div class="info-value">{{ $processo->abertoPor?->name ?? '-' }} em {{ $processo->created_at?->format('d/m/Y H:i') }}</div>
            </div>
            @if($processo->requerente_nome)
                <div class="info-row">
                    <div class="info-label">Requerente:</div>
                    <div class="info-value">
                        {{ $processo->requerente_nome }}
                        @if($processo->requerente_cpf) - CPF {{ $processo->requerente_cpf }}@endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(!empty($processo->dados_formulario))
        <div class="section">
            <div class="section-title">Dados do Formulario</div>
            <div class="info-grid">
                @foreach($processo->dados_formulario as $campo => $valor)
                    <div class="info-row">
                        <div class="info-label">{{ ucfirst($campo) }}:</div>
                        <div class="info-value">{{ $valor }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Decisao</div>
        @php
            $cls = match($processo->decisao) {
                'deferido' => 'decisao-deferido',
                'indeferido' => 'decisao-indeferido',
                'parcial' => 'decisao-parcial',
                default => 'decisao-deferido',
            };
            $titulo = match($processo->decisao) {
                'deferido' => 'DEFERIDO',
                'indeferido' => 'INDEFERIDO',
                'parcial' => 'DEFERIDO PARCIALMENTE',
                default => strtoupper($processo->decisao ?? '-'),
            };
        @endphp
        <div class="decisao-box {{ $cls }}">
            <div class="decisao-titulo">{{ $titulo }}</div>
            <div class="decisao-sub">Decidido em {{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    @if($processo->observacao_conclusao)
        <div class="section">
            <div class="section-title">Parecer / Justificativa</div>
            <div class="parecer">{{ $processo->observacao_conclusao }}</div>
        </div>
    @endif

    @if($processo->tramitacoes && $processo->tramitacoes->count() > 0)
        <div class="section">
            <div class="section-title">Historico de Tramitacao</div>
            <table class="tramitacoes">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Setor</th>
                        <th>Despachado por</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($processo->tramitacoes as $t)
                        <tr>
                            <td>{{ $t->ordem }}</td>
                            <td>{{ $t->setor_destino ?? '-' }}</td>
                            <td>{{ $t->remetente?->name ?? '-' }}</td>
                            <td>{{ $t->despachado_em?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="lei">
        <strong>Base legal:</strong> Lei 14.063/2020, art. 4&deg;, III - Decisao administrativa que exige
        assinatura eletronica qualificada (ICP-Brasil) para validade juridica.
    </div>

    <div class="assinatura-area">
        <p>Aguardando assinatura digital ICP-Brasil</p>
        <div class="assinatura-line"></div>
        <div class="assinatura-nome">{{ $autor?->name ?? '-' }}</div>
        @if($autor?->cpf)
            <div style="font-size: 10px; color: #666;">CPF: {{ $autor->cpf }}</div>
        @endif
    </div>

    <div class="footer">
        Documento gerado automaticamente pelo Sistema GPE Flow / Conceito Gestao Publica<br>
        A validade juridica deste documento esta condicionada a assinatura digital ICP-Brasil
    </div>
</body>
</html>
