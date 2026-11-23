{{-- E-mail não aceita CSS externo nem flex: layout em tabela e estilo inline. O bgcolor é a cor
     dos clientes que ignoram gradiente (Outlook). O ícone vai embutido: o endereço do tenant
     pode não abrir fora da rede, e muito cliente bloqueia imagem remota. --}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GPE Docs — Redefinição de senha</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#334155;">
    <div style="display:none;max-height:0;overflow:hidden;">GPE Docs: use o link para criar uma nova senha. Ele expira em {{ $expira }} minutos.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f5f9">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff"
                    style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

                    <tr>
                        <td bgcolor="#0e7490" style="padding:24px 32px;background-color:#0e7490;background-image:linear-gradient(90deg,#0891b2,#155e75);">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td bgcolor="#ffffff" width="52" height="52" align="center" valign="middle" style="width:52px;height:52px;background-color:#ffffff;border-radius:12px;">
                                        <img src="{{ $message->embed(public_path('images/logo-gpe-docs-icon-email.png')) }}" width="40" height="40" alt="GPE Docs" style="display:block;border:0;">
                                    </td>
                                    <td style="padding-left:14px;color:#ffffff;">
                                        <div style="font-size:26px;line-height:30px;font-weight:bold;">GPE Docs</div>
                                        <div style="font-size:13px;line-height:18px;color:#cffafe;">Gestão Eletrônica de Documentos e Processos</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <h1 style="margin:0 0 16px;font-size:20px;line-height:28px;color:#1e293b;">Redefinição de senha do GPE Docs</h1>
                            <p style="margin:0 0 12px;font-size:15px;line-height:23px;">Olá, {{ $nome }}.</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:23px;">Recebemos uma solicitação para redefinir a senha da sua conta no GPE Docs. Se foi você, clique no botão abaixo para criar uma nova senha.</p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                <tr>
                                    <td bgcolor="#0e7490" align="center" style="background-color:#0e7490;border-radius:10px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Redefinir senha no GPE Docs</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:14px;line-height:21px;text-align:center;color:#64748b;">O link expira em <strong style="color:#334155;">{{ $expira }} minutos</strong>.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td bgcolor="#ecfeff" style="padding:12px 16px;background-color:#ecfeff;border:1px solid #a5f3fc;border-radius:8px;font-size:14px;line-height:21px;color:#155e75;">
                                        Não foi você? Ignore este e-mail: sua senha do GPE Docs continua a mesma.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <p style="margin:0 0 4px;font-size:12px;line-height:18px;color:#64748b;">Se o botão não funcionar, copie e cole este endereço no navegador:</p>
                            <p style="margin:0;font-size:12px;line-height:18px;word-break:break-all;"><a href="{{ $url }}" style="color:#0e7490;">{{ $url }}</a></p>
                        </td>
                    </tr>

                    <tr>
                        <td bgcolor="#f8fafc" align="center" style="padding:16px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:18px;color:#64748b;">
                            Atenciosamente, equipe GPE Docs / Conceito Gestão Pública.<br>
                            Mensagem automática do GPE Docs, não é preciso responder.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
