<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ofício {{ $oficio->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:640px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:linear-gradient(90deg,#0e7490,#45b5bb); color:#fff; padding:24px;">
            <p style="margin:0; font-size:11px; letter-spacing:2px; opacity:.9; text-transform:uppercase;">{{ $orgao }}</p>
            <h1 style="margin:8px 0 0; font-size:20px;">Ofício {{ $oficio->numero }}</h1>
            <p style="margin:6px 0 0; font-size:14px; opacity:.95;">{{ $oficio->assunto }}</p>
        </div>

        <div style="padding:24px; font-size:14px; color:#111827;">
            <p style="margin:0 0 16px;">
                Para: <strong>{{ $oficio->destinatario_nome }}</strong>
                @if ($oficio->destinatario_cargo) — {{ $oficio->destinatario_cargo }} @endif
                @if ($oficio->destinatario_orgao)<br>{{ $oficio->destinatario_orgao }} @endif
            </p>

            {{-- HTML já limpo e com as variáveis trocadas (Oficio::conteudoFinal). --}}
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; line-height:1.6;">{!! $conteudo !!}</div>

            <p style="text-align:center; margin:24px 0 8px;">
                <a href="{{ $link }}" style="display:inline-block; background:#0e7490; color:#fff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:bold;">
                    Confirmar o recebimento
                </a>
            </p>
            <p style="font-size:12px; color:#6b7280; text-align:center; margin:0 0 16px;">
                O ofício em PDF segue anexo. Ao abrir o link, o recebimento fica registrado para o remetente.
            </p>

            <p style="font-size:12px; color:#6b7280; word-break:break-all;">Se o botão não abrir, copie este endereço: {{ $link }}</p>
            <p style="font-size:11px; color:#9ca3af;">Mensagem enviada pelo sistema de gestão de documentos de {{ $orgao }}.</p>
        </div>
    </div>
    <img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block; width:1px; height:1px; border:0;">
</body>
</html>
