<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Participação no protocolo {{ $p->numero_protocolo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Olá, {{ $nome }}.</p>
            <p>Você foi indicado(a) como <strong>{{ $papel }}</strong> no protocolo <strong>{{ $p->numero_protocolo }}</strong> — {{ $p->assunto }}.</p>
            <p>Confirme seus dados (CPF/CNPJ, registro profissional e contato) para acompanhar o processo pelo portal:</p>
            <p style="text-align:center; margin:24px 0;"><a href="{{ $link }}" style="background:#0891b2; color:#fff; padding:12px 22px; border-radius:8px; text-decoration:none; font-weight:bold;">Confirmar participação</a></p>
            <p style="font-size:12px; color:#6b7280;">Se você não reconhece este protocolo, ignore esta mensagem.</p>
            @include('emails.partials.rodape-descadastro')
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>