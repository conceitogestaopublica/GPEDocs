<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Documento para assinatura</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:linear-gradient(90deg,#0e7490,#45b5bb); color:#fff; padding:24px;">
            <p style="margin:0; font-size:11px; letter-spacing:2px; opacity:.9; text-transform:uppercase;">{{ $orgao }}</p>
            <h1 style="margin:8px 0 0; font-size:20px;">Documento para sua assinatura</h1>
        </div>

        <div style="padding:24px; font-size:14px; color:#111827;">
            <p>Olá, {{ $nome }}.</p>
            <p>{{ $orgao }} enviou o documento abaixo para você assinar eletronicamente.</p>

            <div style="background:#f5f7fb; border-left:4px solid #45b5bb; padding:12px 16px; margin:16px 0; border-radius:6px;">
                <p style="margin:0; font-weight:bold;">{{ $documento }}</p>
                @if ($mensagem)
                    <p style="margin:8px 0 0; font-size:13px; color:#374151; white-space:pre-line;">{{ $mensagem }}</p>
                @endif
            </div>

            <p style="text-align:center; margin:24px 0;">
                <a href="{{ $link }}" style="display:inline-block; background:#0e7490; color:#fff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:bold;">
                    Ver e assinar o documento
                </a>
            </p>

            <p style="font-size:12px; color:#6b7280;">
                Para assinar, você entra com o seu cadastro (ou cria um, com o seu CPF).
                @if ($expira_em) O link vale até {{ $expira_em }}. @endif
            </p>
            <p style="font-size:12px; color:#6b7280; word-break:break-all;">Se o botão não abrir, copie este endereço: {{ $link }}</p>
            <p style="font-size:12px; color:#6b7280;">Se você não esperava este documento, ignore esta mensagem.</p>
        </div>
    </div>
    @include('emails.partials.rodape-descadastro')
</body>
</html>
