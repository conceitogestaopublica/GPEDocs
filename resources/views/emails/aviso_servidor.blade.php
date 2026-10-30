<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $n->titulo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Olá, {{ $nome }}.</p>
            <p style="font-size:16px; font-weight:bold; margin-bottom:6px;">{{ $n->titulo }}</p>
            @if ($n->mensagem)
                <p style="color:#374151; margin-top:0;">{{ $n->mensagem }}</p>
            @endif
            @if ($link)
                <p style="margin:24px 0;">
                    <a href="{{ $link }}" style="background:#0891b2; color:#fff; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:bold;">Abrir no GPE Docs</a>
                </p>
            @endif
            <p style="font-size:12px; color:#6b7280; border-top:1px solid #e5e7eb; padding-top:12px; margin-bottom:0;">
                Você recebe este aviso porque escolheu ser avisado por e-mail. Para mudar, entre no GPE Docs em
                <b>Meu perfil › Preferências</b>.
            </p>
        </div>
    </div>
</body>
</html>
