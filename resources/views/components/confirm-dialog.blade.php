{{--
    Confirmação padrão para páginas Blade — irmã do ConfirmDialog React
    (resources/js/Components/ConfirmDialog.jsx), com os MESMOS nomes de prop e os
    mesmos três tons.

    Existe porque o painel do landlord é Blade puro (Tailwind por CDN, sem Vite,
    sem React): o `useConfirm()` do app não alcança essas telas. Em vez de um
    `window.confirm` — que não segue o design system e trata rotina igual a
    decisão consequente — inclua este componente UMA vez na página e chame:

        <x-confirm-dialog />

        // dentro do seu <script>
        if (await confirmar({ titulo: 'Excluir?', tom: 'atencao' })) { ... }

        // em formulário, sem precisar de async:
        <form onsubmit="return confirmarSubmit(this, @js(['titulo' => 'Excluir?', 'tom' => 'atencao']))">

    Props aceitas em `confirmar(opts)` (ou uma string, como atalho):
        titulo, descricao, detalhes[], tom (info|atencao|perigo),
        rotuloConfirmar, rotuloCancelar, exigirTexto

    `confirmar()` devolve Promise<boolean>. `confirmarSubmit()` devolve false na
    hora e reenvia o formulário sozinho quando o usuário confirma.
--}}

<div id="cfDlg" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     role="dialog" aria-modal="true">
    <div id="cfBox" class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl">
        <div id="cfHead" class="flex items-start gap-3 px-5 py-4">
            <i id="cfIcon" class="mt-0.5 text-lg"></i>
            <h3 id="cfTitulo" class="text-base font-semibold text-slate-800"></h3>
        </div>
        <div class="space-y-3 px-5 py-4">
            <div id="cfDesc" class="hidden text-sm leading-relaxed text-slate-600"></div>
            <ul id="cfDet" class="hidden space-y-1.5 rounded-lg border px-3 py-2.5 text-sm"></ul>
            <label id="cfExigirWrap" class="hidden block">
                <span class="text-xs text-slate-500">
                    Para confirmar, digite <strong id="cfExigirTxt" class="font-semibold text-slate-700"></strong>:
                </span>
                <input id="cfExigirInput" autocomplete="off"
                       class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
            <button type="button" id="cfCancelar"
                    class="px-3 py-2 text-sm rounded hover:bg-slate-200 text-slate-700"></button>
            <button type="button" id="cfConfirmar"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-40"></button>
        </div>
    </div>
</div>

<script>
(function () {
    // Mesmos três tons do ConfirmDialog React: info = rotina, atencao = perde
    // dado, perigo = irreversível.
    const TONS = {
        info:    { icone: 'fas fa-circle-question',    cab: 'bg-sky-50',   cor: 'text-sky-600',   caixa: 'bg-sky-50 border-sky-200 text-sky-900',       btn: 'bg-blue-600 hover:bg-blue-700' },
        atencao: { icone: 'fas fa-triangle-exclamation', cab: 'bg-amber-50', cor: 'text-amber-600', caixa: 'bg-amber-50 border-amber-200 text-amber-900', btn: 'bg-amber-600 hover:bg-amber-700' },
        perigo:  { icone: 'fas fa-circle-exclamation', cab: 'bg-rose-50',  cor: 'text-rose-600',  caixa: 'bg-rose-50 border-rose-200 text-rose-900',    btn: 'bg-rose-600 hover:bg-rose-700' },
    };

    const el = (id) => document.getElementById(id);
    let resolver = null;

    function fechar(resposta) {
        const r = resolver;
        resolver = null;
        el('cfDlg').classList.add('hidden');
        if (r) r(resposta);
    }

    function liberado(exigir) {
        return !exigir || el('cfExigirInput').value.trim().toUpperCase() === exigir.toUpperCase();
    }

    window.confirmar = function (opcoes) {
        if (resolver) resolver(false);            // chamada nova cancela a anterior
        const o = typeof opcoes === 'string' ? { titulo: opcoes } : (opcoes || {});
        const t = TONS[o.tom] || TONS.info;

        el('cfHead').className = 'flex items-start gap-3 px-5 py-4 ' + t.cab;
        el('cfIcon').className = t.icone + ' mt-0.5 text-lg ' + t.cor;
        el('cfTitulo').textContent = o.titulo || 'Confirmar';

        const desc = el('cfDesc');
        desc.textContent = o.descricao || '';
        desc.classList.toggle('hidden', !o.descricao);

        const det = el('cfDet');
        det.innerHTML = '';
        const itens = o.detalhes || [];
        det.classList.toggle('hidden', itens.length === 0);
        det.className = itens.length
            ? 'space-y-1.5 rounded-lg border px-3 py-2.5 text-sm ' + t.caixa
            : 'hidden';
        itens.forEach((d) => {
            const li = document.createElement('li');
            li.className = 'flex gap-2';
            li.innerHTML = '<i class="fas fa-angle-right mt-1 shrink-0 text-xs opacity-60"></i>';
            const s = document.createElement('span');
            s.textContent = d;                     // textContent: nada de HTML vindo de dado
            li.appendChild(s);
            det.appendChild(li);
        });

        const exigir = o.exigirTexto || null;
        el('cfExigirWrap').classList.toggle('hidden', !exigir);
        if (exigir) {
            el('cfExigirTxt').textContent = exigir;
            el('cfExigirInput').value = '';
            el('cfExigirInput').placeholder = exigir;
        }

        const btn = el('cfConfirmar');
        btn.textContent = o.rotuloConfirmar || 'Confirmar';
        btn.className = 'px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-40 ' + t.btn;
        btn.disabled = !liberado(exigir);
        btn.onclick = () => { if (liberado(exigir)) fechar(true); };

        el('cfCancelar').textContent = o.rotuloCancelar || 'Cancelar';
        el('cfExigirInput').oninput = () => { btn.disabled = !liberado(exigir); };

        el('cfDlg').classList.remove('hidden');
        if (exigir) el('cfExigirInput').focus();

        return new Promise((resolve) => { resolver = resolve; });
    };

    // Formulário: devolve false agora e reenvia sozinho se o usuário confirmar.
    window.confirmarSubmit = function (form, opcoes) {
        if (form.dataset.cfOk === '1') { delete form.dataset.cfOk; return true; }
        confirmar(opcoes).then((ok) => {
            if (!ok) return;
            form.dataset.cfOk = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
        return false;
    };

    el('cfCancelar').addEventListener('click', () => fechar(false));
    el('cfDlg').addEventListener('click', (e) => { if (e.target === el('cfDlg')) fechar(false); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !el('cfDlg').classList.contains('hidden')) fechar(false);
    });
})();
</script>
