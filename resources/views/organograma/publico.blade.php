{{-- Organograma público (TR 4.12.8): para incorporar no site da prefeitura por iframe. Só setores ativos e contatos. --}}
@php
    $render = function (array $nos, int $nivel = 0) use (&$render) {
        $html = '';
        foreach ($nos as $n) {
            $busca = e(mb_strtolower(trim($n['nome'] . ' ' . ($n['sigla'] ?? '') . ' ' . ($n['responsavel']['nome'] ?? ''))));
            $contato = array_filter([
                $n['telefone'] ? '<span>&#9742; ' . e($n['telefone']) . ($n['ramal'] ? ' · ramal ' . e($n['ramal']) : '') . '</span>' : ($n['ramal'] ? '<span>Ramal ' . e($n['ramal']) . '</span>' : null),
                $n['email'] ? '<a href="mailto:' . e($n['email']) . '">' . e($n['email']) . '</a>' : null,
                $n['responsavel'] ? '<span>Responsável: ' . e($n['responsavel']['nome']) . ($n['responsavel']['cargo'] ? ' (' . e($n['responsavel']['cargo']) . ')' : '') . '</span>' : null,
            ]);
            $cab = '<span class="nome">' . e($n['nome']) . '</span>' . ($n['sigla'] ? ' <span class="sigla">' . e($n['sigla']) . '</span>' : '')
                . ($contato ? '<div class="contato">' . implode('', $contato) . '</div>' : '');
            $html .= $n['filhos']
                ? '<li data-busca="' . $busca . '"><details' . ($nivel < 1 ? ' open' : '') . '><summary>' . $cab . '</summary><ul>' . $render($n['filhos'], $nivel + 1) . '</ul></details></li>'
                : '<li data-busca="' . $busca . '"><div class="folha">' . $cab . '</div></li>';
        }
        return $html;
    };
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Organograma — {{ $ug->nome }}</title>
    <style>
        :root { --cor: #0e7490; --borda: #e5e7eb; --texto: #1f2937; --suave: #6b7280; --fundo: #fff; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--texto); background: var(--fundo); }
        h1 { font-size: 18px; margin: 0 0 4px; color: var(--cor); }
        .sub { color: var(--suave); font-size: 12px; margin: 0 0 12px; }
        input[type=search] { width: 100%; max-width: 420px; padding: 8px 10px; border: 1px solid var(--borda); border-radius: 8px; font: inherit; margin-bottom: 12px; }
        ul { list-style: none; margin: 0; padding-left: 18px; border-left: 1px dashed var(--borda); }
        body > ul { padding-left: 0; border-left: 0; }
        li { margin: 4px 0; }
        summary { cursor: pointer; padding: 6px 8px; border-radius: 8px; }
        summary:hover, .folha:hover { background: #f0f9ff; }
        .folha { padding: 6px 8px 6px 22px; border-radius: 8px; }
        .nome { font-weight: 600; }
        .sigla { font-size: 10px; font-weight: 700; color: var(--cor); background: #ecfeff; border-radius: 4px; padding: 1px 5px; }
        .contato { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12px; color: var(--suave); margin-top: 2px; }
        .contato a { color: var(--cor); }
        .vazio { color: var(--suave); display: none; }
        footer { margin-top: 16px; font-size: 11px; color: var(--suave); }
    </style>
</head>
<body>
    <h1>{{ $ug->nome }}</h1>
    <p class="sub">Organograma e contatos dos setores</p>
    <input type="search" id="busca" placeholder="Buscar setor, sigla ou responsável…" aria-label="Buscar setor">
    <ul id="arvore">{!! $render($arvore) !!}</ul>
    <p class="vazio" id="vazio">Nenhum setor encontrado.</p>
    <footer>Atualizado automaticamente a partir do cadastro de setores · GPE Docs</footer>
    <script>
        (function () {
            var campo = document.getElementById('busca'), itens = document.querySelectorAll('#arvore li'), vazio = document.getElementById('vazio');
            var sem = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
            campo.addEventListener('input', function () {
                var t = sem(campo.value.trim()), algum = false;
                itens.forEach(function (li) { li.style.display = ''; li.dataset.ok = ''; });
                if (t) {
                    // Mostra quem casa e os setores acima dele; abre os ramos
                    itens.forEach(function (li) {
                        if (sem(li.dataset.busca).indexOf(t) === -1) return;
                        algum = true;
                        for (var p = li; p && p.id !== 'arvore'; p = p.parentElement) {
                            if (p.tagName === 'LI') p.dataset.ok = '1';
                            if (p.tagName === 'DETAILS') p.open = true;
                        }
                    });
                    itens.forEach(function (li) { if (!li.dataset.ok) li.style.display = 'none'; });
                }
                vazio.style.display = t && !algum ? 'block' : 'none';
            });
        })();
    </script>
</body>
</html>
