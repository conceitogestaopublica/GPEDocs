<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $a->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div style="background:#0891b2; color:#fff; padding:16px 24px; font-size:13px;">Suporte — atendimento <strong>{{ $a->numero }}</strong></div>
        <div style="padding:24px; font-size:14px; color:#111827; line-height:1.5;">
            @if ($tipo === 'resolvido')
                <p style="margin-top:0;">Seu atendimento <strong>{{ $a->numero }}</strong> — {{ $a->titulo }} — foi marcado como <strong>resolvido</strong>.</p>
                <table style="font-size:13px; border-collapse:collapse; margin:8px 0 16px;">
                    <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Aberto em</td><td>{{ $resumo['aberto_em'] }}</td></tr>
                    <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Primeira resposta</td><td>{{ $resumo['primeira_resposta_em'] ?? '—' }}</td></tr>
                    <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Resolvido em</td><td>{{ $resumo['resolvido_em'] ?? '—' }}</td></tr>
                    @foreach ($resumo['atendentes'] as $at)
                        <tr><td style="color:#64748b; padding:2px 12px 2px 0;">Atendente</td><td>{{ $at['nome'] }} ({{ $at['de'] }} a {{ $at['ate'] }})</td></tr>
                    @endforeach
                </table>
            @else
                <p style="margin-top:0;">Recebemos seu pedido de suporte <strong>{{ $a->titulo }}</strong>. Guarde o número <strong>{{ $a->numero }}</strong> para retomar o atendimento.</p>
            @endif

            <div style="border-top:1px solid #e5e7eb; margin-top:8px; padding-top:8px;">
                @foreach ($mensagens as $m)
                    <p style="margin:8px 0; font-size:13px;">
                        <span style="color:#64748b;">{{ $m->tipo === 'sistema' ? 'Sistema' : ($m->name ?? '—') }} · {{ \Illuminate\Support\Carbon::parse($m->created_at, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m H:i') }}</span><br>
                        {!! nl2br(e($m->conteudo)) !!}
                    </p>
                @endforeach
            </div>

            @if ($tipo === 'resolvido')
                <p style="margin-top:20px;">A solução resolveu seu problema?</p>
                <p>
                    <a href="{{ $avaliar }}" style="display:inline-block; background:#16a34a; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; margin-right:8px;">Sim, avaliar o atendimento</a>
                    <a href="{{ $reabrir }}" style="display:inline-block; background:#fff; color:#b91c1c; border:1px solid #b91c1c; padding:9px 17px; border-radius:8px; text-decoration:none;">Não, reabrir</a>
                </p>
            @endif
            <p style="font-size:12px; color:#6b7280;">Você também pode responder este e-mail: a resposta entra na conversa do atendimento. <a href="{{ $link }}">Abrir no sistema</a></p>
        </div>
    </div>
    @if (! empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0">@endif
</body>
</html>
