<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Protocolo {{ $p->numero_protocolo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Olá{{ $p->requerente_nome ? ', ' . $p->requerente_nome : '' }}.</p>
            <p>Seu pedido foi protocolado sob o número <strong>{{ $p->numero_protocolo }}</strong> em {{ $p->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.</p>
            <p><strong>Assunto:</strong> {{ $p->assunto }}</p>
            @if(count($anexados))<p><strong>Cópia dos documentos entregues</strong> (anexos a este e-mail):</p><ul>@foreach($anexados as $n)<li>{{ $n }}</li>@endforeach</ul>@endif
            @if(count($listados))<p>Também entregues (grandes demais para o e-mail):</p><ul>@foreach($listados as $n)<li>{{ $n }}</li>@endforeach</ul>@endif
            <p>Acompanhe pelo portal com o seu CPF/CNPJ, informando o número do protocolo.</p>
            @include('emails.partials.rodape-descadastro')
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>