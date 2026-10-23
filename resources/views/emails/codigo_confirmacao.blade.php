<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Código de confirmação</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="padding:24px; font-size:14px; color:#111827;">
            <p>Olá, {{ $nome }}.</p>
            <p>Use o código abaixo para confirmar o seu e-mail:</p>
            <p style="text-align:center; font-size:32px; letter-spacing:8px; font-weight:bold; color:#0e7490; margin:24px 0;">{{ $codigo }}</p>
            <p style="font-size:12px; color:#6b7280;">O código vale por {{ $minutos }} minutos. Se não foi você que pediu, ignore esta mensagem.</p>
        </div>
    </div>
</body>
</html>
