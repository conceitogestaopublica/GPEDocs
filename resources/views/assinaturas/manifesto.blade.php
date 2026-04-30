<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Manifesto de Assinatura Eletronica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; line-height: 1.5; padding: 30px 40px; }
        .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; color: #1e40af; margin-bottom: 3px; }
        .header p { font-size: 10px; color: #666; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e40af; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; color: #555; padding: 3px 10px 3px 0; width: 160px; font-size: 10px; }
        .info-value { display: table-cell; padding: 3px 0; font-size: 11px; }
        table.assinaturas { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.assinaturas th { background: #1e40af; color: white; padding: 6px 8px; font-size: 9px; text-align: left; text-transform: uppercase; }
        table.assinaturas td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        table.assinaturas tr:nth-child(even) { background: #f8fafc; }
        .status-assinado { color: #16a34a; font-weight: bold; }
        .status-recusado { color: #dc2626; font-weight: bold; }
        .status-pendente { color: #ca8a04; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #1e40af; text-align: center; font-size: 9px; color: #999; }
        .hash { font-family: 'Courier New', monospace; font-size: 9px; word-break: break-all; background: #f1f5f9; padding: 6px 10px; border-radius: 4px; }
        .legal { margin-top: 20px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 9px; color: #666; }
        .qr-placeholder { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MANIFESTO DE ASSINATURA ELETRONICA</h1>
        <p>Conceito Gestao Publica — Plataforma Digital Integrada</p>
        <p>Documento gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Informacoes do Documento</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nome do Documento:</div>
                <div class="info-value">{{ $documento->nome }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo Documental:</div>
                <div class="info-value">{{ $documento->tipoDocumental?->nome ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Autor:</div>
                <div class="info-value">{{ $documento->autor?->name ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Criado em:</div>
                <div class="info-value">{{ $documento->created_at?->format('d/m/Y H:i:s') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Versao:</div>
                <div class="info-value">v{{ $documento->versao_atual }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">{{ ucfirst($documento->status) }}</div>
            </div>
            @if($documento->qr_code_token)
            <div class="info-row">
                <div class="info-label">Codigo de Verificacao:</div>
                <div class="info-value">{{ $documento->qr_code_token }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Solicitacao de Assinatura #{{ $solicitacao->id }}</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Solicitante:</div>
                <div class="info-value">{{ $solicitacao->solicitante?->name }} ({{ $solicitacao->solicitante?->email }})</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data da Solicitacao:</div>
                <div class="info-value">{{ $solicitacao->created_at?->format('d/m/Y H:i:s') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">{{ ucfirst($solicitacao->status) }}</div>
            </div>
            @if($solicitacao->mensagem)
            <div class="info-row">
                <div class="info-label">Mensagem:</div>
                <div class="info-value">{{ $solicitacao->mensagem }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Registro de Assinaturas</div>
        <table class="assinaturas">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Signatario</th>
                    <th>Tipo</th>
                    <th>CPF</th>
                    <th>Status</th>
                    <th>Data/Hora</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($solicitacao->assinaturas as $i => $assinatura)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $assinatura->signatario?->name ?? '-' }}<br><span style="font-size:8px;color:#666">{{ $assinatura->email_signatario }}</span></td>
                    <td>
                        @if($assinatura->tipo_assinatura === 'qualificada')
                            <strong style="color:#16a34a;">Qualificada</strong><br>
                            <span style="font-size:8px;color:#666">ICP-Brasil</span>
                        @elseif($assinatura->tipo_assinatura === 'avancada')
                            Avancada
                        @else
                            Simples
                        @endif
                    </td>
                    <td>{{ $assinatura->cpf_signatario ? substr($assinatura->cpf_signatario, 0, 3) . '.***.' . substr($assinatura->cpf_signatario, -2) : '-' }}</td>
                    <td class="status-{{ $assinatura->status }}">{{ ucfirst($assinatura->status) }}</td>
                    <td>{{ $assinatura->assinado_em?->format('d/m/Y H:i:s') ?? '-' }}</td>
                    <td>{{ $assinatura->ip ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php($qualificadas = $solicitacao->assinaturas->where('tipo_assinatura', 'qualificada'))
    @if($qualificadas->count() > 0)
    <div class="section">
        <div class="section-title">Assinaturas Qualificadas — Detalhes ICP-Brasil</div>
        @foreach($qualificadas as $a)
            <div style="border:1px solid #e2e8f0; padding:10px; margin-bottom:10px; border-radius:4px; background:#f8fafc;">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Signatario:</div>
                        <div class="info-value"><strong>{{ $a->signatario?->name }}</strong></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Titular do Cert.:</div>
                        <div class="info-value">{{ $a->certificado?->subject_cn ?? '-' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">CPF:</div>
                        <div class="info-value">{{ $a->cpf_signatario }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">AC Emissora:</div>
                        <div class="info-value">{{ $a->certificado?->issuer_cn ?? '-' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Numero de Serie:</div>
                        <div class="info-value"><span class="hash">{{ $a->certificado?->serial_number ?? '-' }}</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Validade do Cert.:</div>
                        <div class="info-value">
                            {{ $a->certificado?->valido_de?->format('d/m/Y') }}
                            ate
                            {{ $a->certificado?->valido_ate?->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Thumbprint SHA-256:</div>
                        <div class="info-value"><span class="hash">{{ $a->certificado?->thumbprint_sha256 ?? '-' }}</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Algoritmo:</div>
                        <div class="info-value">{{ $a->algoritmo_hash ?? 'SHA-256' }} com RSA</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Politica:</div>
                        <div class="info-value">{{ $a->politica_assinatura ?? 'AD-RB v2 (OID 2.16.76.1.7.1.1.2.3)' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Carimbo de Tempo:</div>
                        <div class="info-value">{{ $a->timestamp_assinatura?->format('d/m/Y H:i:s') ?? $a->assinado_em?->format('d/m/Y H:i:s') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Hash do Documento:</div>
                        <div class="info-value"><span class="hash">{{ $a->hash_documento ?? '-' }}</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Hash do Envelope PKCS#7:</div>
                        <div class="info-value"><span class="hash">{{ $a->hash_assinatura_sha256 ?? '-' }}</span></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if($solicitacao->assinaturas->where('status', 'assinado')->count() > 0)
    <div class="section">
        <div class="section-title">Hashes de Integridade (SHA-256)</div>
        @foreach($solicitacao->assinaturas->where('status', 'assinado') as $assinatura)
        <p style="margin-bottom: 6px;">
            <strong>{{ $assinatura->signatario?->name }}:</strong><br>
            <span class="hash">{{ $assinatura->hash_documento ?? 'N/A' }}</span>
        </p>
        @endforeach
    </div>
    @endif

    <div class="legal">
        <strong>Base Legal — Lei 14.063/2020:</strong>
        <br><br>
        <strong>Art. 4, I (Simples):</strong> identifica o signatario por meio de email, CPF, IP, geolocalizacao e hash do documento.<br>
        <strong>Art. 4, II (Avancada):</strong> usa certificado digital nao ICP-Brasil, com vinculo univoco ao signatario.<br>
        <strong>Art. 4, III (Qualificada):</strong> usa certificado digital ICP-Brasil — equivale juridicamente a assinatura manuscrita
        e e o unico tipo aceito sem restricao em qualquer interacao com o poder publico (Decreto 10.543/2020).<br><br>
        <strong>Padrao tecnico:</strong> assinaturas qualificadas neste manifesto seguem PAdES-BES com politica AD-RB v2
        (DOC-ICP-15.03 do ITI), algoritmo SHA-256 com RSA. A integridade pode ser verificada reabrindo o PDF em qualquer
        leitor compativel (Adobe Reader, ITI Verificador) — a assinatura embutida e o /ByteRange permitem validacao offline
        contra a cadeia da AC Raiz da ICP-Brasil.
    </div>

    <div class="footer">
        <p><strong>Conceito Gestao Publica</strong> — Plataforma Digital Integrada</p>
        <p>Documento gerado automaticamente. Verificacao: {{ config('app.url') }}/verificar/{{ $documento->qr_code_token }}</p>
    </div>
</body>
</html>
