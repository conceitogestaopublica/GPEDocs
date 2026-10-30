<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Inscrição {{ $i->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">{{ $orgao }}</div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            <p style="margin-top:0;">Olá, {{ $i->nome }}.</p>
            <p>Sua inscrição <strong>{{ $i->numero }}</strong> no processo seletivo <strong>{{ $pss?->titulo }}</strong>@if($i->cargo) (cargo: {{ $i->cargo }})@endif está <strong>{{ $situacao }}</strong>.</p>
            @if($i->resposta)<div style="background:#f1f5f9; border-left:4px solid #0891b2; padding:12px 16px; white-space:pre-wrap;">{{ $i->resposta }}</div>@endif
            <p style="font-size:12px; color:#6b7280; border-top:1px solid #e5e7eb; padding-top:12px; margin-top:24px;">
                Acompanhe pelo portal, em Minhas inscrições.
            </p>
            @include('emails.partials.rodape-descadastro')
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>