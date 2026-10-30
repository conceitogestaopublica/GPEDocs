<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Documento recusado — {{ $p->numero_protocolo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Olá{{ $p->requerente_nome ? ', ' . $p->requerente_nome : '' }}.</p>
            <p>Na análise do protocolo <strong>{{ $p->numero_protocolo }}</strong> — {{ $p->assunto }}, o documento <strong>{{ $documento }}</strong> foi <strong>recusado</strong>:</p>
            <div style="background:#fef2f2; border-left:4px solid #dc2626; padding:12px 16px; white-space:pre-wrap;">{{ $motivo }}</div>
            <p>Segue anexa a cópia com as anotações da análise. Corrija e envie de novo pelo portal ou no atendimento, informando o número do protocolo.</p>
            @include('emails.partials.rodape-descadastro')
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>