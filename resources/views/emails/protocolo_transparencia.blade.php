<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Protocolo {{ $codigo }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Seu pedido foi registrado pelo Portal da Transparência.</p>
            <p>
                Código: <strong>{{ $codigo }}</strong><br>
                Chave: <strong>{{ $chave }}</strong><br>
                Processo: <strong>{{ $p->numero_protocolo }}</strong>
                @if ($p->prazo_legal_em)
                    <br>Prazo de resposta: <strong>{{ $p->prazo_legal_em->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}</strong>
                @endif
            </p>
            <p>Guarde o código e a chave: com eles você acompanha o andamento. Não os repasse a outra pessoa.</p>
            @if ($url)
                <p><a href="{{ $url }}" style="color:#0891b2;">Acompanhar no Portal da Transparência</a></p>
            @endif
            @include('emails.partials.rodape-descadastro')
        </div>
    </div>
</body>
</html>
