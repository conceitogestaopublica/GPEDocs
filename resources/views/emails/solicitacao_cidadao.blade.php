<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $solicitacao->codigo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:linear-gradient(90deg,#2563eb,#4f46e5); color:#fff; padding:24px;">
            <p style="margin:0; font-size:11px; letter-spacing:2px; opacity:.9; text-transform:uppercase;">Portal do Cidadao — {{ $ug->nome }}</p>
            <h1 style="margin:8px 0 0; font-size:20px;">Solicitacao {{ $solicitacao->codigo }}</h1>
        </div>

        <div style="padding:24px;">
            @if ($evento === 'criada')
                <p>Sua solicitacao foi registrada com sucesso!</p>
                <p>Acompanharemos o andamento e voce sera notificado por e-mail quando houver atualizacoes.</p>
            @elseif ($evento === 'status_alterado')
                <p>O status da sua solicitacao foi atualizado.</p>
                <p><strong>Status atual:</strong> {{ $statusLabel }}</p>
                @if ($solicitacao->resposta)
                    <div style="background:#f5f7fb; border-left:4px solid #2563eb; padding:12px 16px; margin:12px 0; border-radius:6px;">
                        <p style="margin:0; font-size:13px; color:#374151; white-space:pre-line;">{{ $solicitacao->resposta }}</p>
                    </div>
                @endif
            @elseif ($evento === 'comentario')
                <p>Voce recebeu uma nova mensagem do atendente:</p>
                <div style="background:#f5f7fb; border-left:4px solid #2563eb; padding:12px 16px; margin:12px 0; border-radius:6px;">
                    <p style="margin:0; font-size:13px; color:#374151; white-space:pre-line;">{{ $mensagemExtra }}</p>
                </div>
            @endif

            <table style="width:100%; border-collapse:collapse; margin-top:16px; font-size:13px;">
                <tr>
                    <td style="padding:8px 0; color:#6b7280; width:140px;">Servico</td>
                    <td style="padding:8px 0; color:#111827;"><strong>{{ $servico->titulo }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:#6b7280;">Codigo</td>
                    <td style="padding:8px 0; color:#111827;"><strong>{{ $solicitacao->codigo }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:#6b7280;">Status</td>
                    <td style="padding:8px 0;"><span style="background:#dbeafe; color:#1e40af; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:bold;">{{ $statusLabel }}</span></td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:#6b7280;">Registrado em</td>
                    <td style="padding:8px 0; color:#111827;">{{ $solicitacao->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            @if ($temPdfAssinado ?? false)
                <div style="background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:12px 16px; margin-top:16px; font-size:13px; color:#166534;">
                    <strong><i>📎 Documento assinado digitalmente em anexo</i></strong>
                    <p style="margin:4px 0 0; font-size:12px; color:#15803d;">
                        A decisao foi assinada com certificado digital ICP-Brasil (Lei 14.063/2020) e esta anexa a este e-mail.
                    </p>
                </div>
            @endif

            <div style="text-align:center; margin:24px 0 8px;">
                <a href="{{ $urlPortal }}/minhas-solicitacoes/{{ $solicitacao->id }}"
                   style="display:inline-block; background:#2563eb; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:14px;">
                    Ver detalhes no portal
                </a>
            </div>

            <p style="font-size:11px; color:#9ca3af; text-align:center; margin-top:24px;">
                Este e-mail foi enviado automaticamente. Para responder, acesse o portal e use a area de comentarios.
            </p>
        </div>
    </div>
</body>
</html>
