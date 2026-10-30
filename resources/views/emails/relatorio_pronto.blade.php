<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $nome }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">O relatório agendado <strong>{{ $nome }}</strong> foi gerado.</p>
            <table style="font-size:13px; border-collapse:collapse;">
                <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Relatório</td><td>{{ $titulo }}</td></tr>
                <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Filtros</td><td>{{ $filtros }}</td></tr>
                <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Linhas</td><td>{{ $linhas }}</td></tr>
            </table>
            <p>{{ $conteudo !== null ? 'O arquivo CSV segue em anexo.' : 'O arquivo é grande demais para o e-mail.' }} Ele também fica disponível no sistema por 7 dias:</p>
            <p><a href="{{ $link }}" style="display:inline-block; background:#0891b2; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none;">Abrir no sistema</a></p>
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>
