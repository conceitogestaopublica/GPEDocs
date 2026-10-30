<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha de inscrição - {{ $i->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #222; line-height: 1.5; padding: 30px 40px; }
        .numero { text-align: center; margin: 24px 0 4px; font-size: 22px; font-weight: bold; color: #0e7490; }
        .sub { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; }
        h3 { font-size: 12px; color: #0e7490; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin: 14px 0 6px; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { width: 32%; text-align: left; vertical-align: top; background: #f1f5f9; padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; color: #475569; }
        table.dados td { padding: 6px 8px; border: 1px solid #cbd5e1; }
        .nota { margin-top: 20px; font-size: 9px; color: #555; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.cabecalho-ug', ['ug' => $ug ?? null, 'titulo' => 'Ficha de inscrição', 'subtitulo' => $i->numero, 'usuario' => $i->nome])

    <p class="numero">{{ $i->numero }}</p>
    <p class="sub">{{ $pss->titulo }}@if($pss->edital_numero) — Edital {{ $pss->edital_numero }}@endif</p>

    <h3>Candidato</h3>
    <table class="dados">
        <tr><th>Nome</th><td>{{ $i->nome }}</td></tr>
        <tr><th>CPF</th><td>{{ \App\Support\DocumentoPessoa::mascararCpf($i->cpf) }}</td></tr>
        @if($i->cargo)<tr><th>Cargo</th><td>{{ $i->cargo }}</td></tr>@endif
        <tr><th>E-mail</th><td>{{ $i->email ?: '-' }}</td></tr>
        <tr><th>Telefone</th><td>{{ $i->telefone ?: '-' }}</td></tr>
        <tr><th>Inscrito em</th><td>{{ $i->created_at?->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Situação</th><td>{{ \App\Models\Processo\PssInscricao::SITUACOES[$i->situacao] ?? $i->situacao }}</td></tr>
    </table>

    @if(! empty($i->dados))
    <h3>Dados da inscrição</h3>
    <table class="dados">
        @foreach($i->dados as $campo => $valor)
            <tr><th>{{ $rotulos[$campo] ?? str_replace('_', ' ', $campo) }}</th>
                <td>{{ is_array($valor) ? implode(', ', array_filter($valor)) : (is_bool($valor) ? ($valor ? 'Sim' : 'Não') : $valor) }}</td></tr>
        @endforeach
    </table>
    @endif

    <h3>Documentos enviados</h3>
    @if($i->anexos->isEmpty())<p>Nenhum.</p>@else<ul style="margin-left: 16px;">@foreach($i->anexos as $a)<li>{{ $a->nome }}</li>@endforeach</ul>@endif

    <p class="nota">Guarde esta ficha. O resultado e as respostas aparecem no portal, em Minhas inscrições, e chegam pelo e-mail informado.</p>
</body>
</html>