import { useCallback, useEffect, useRef } from 'react';

/**
 * Faz o container de scroll de uma LISTAGEM preencher a altura que sobra até o fim da
 * viewport, rolando SÓ internamente — a página inteira nunca rola. Padrão do gpe2.
 *
 * Cálculo top-down: mede o topo real do elemento e reserva abaixo dele o próximo irmão
 * (paginação, se houver) + o `<footer>` do layout + `gap` (paddings dos containers + respiro).
 * Depois CORRIGE pelo que de fato sobrou: se a página ainda passa da viewport (ex.: paginação
 * fora do card, que o cálculo não enxerga), desconta o excesso. Tabelas curtas nunca são
 * cortadas; só as altas são limitadas. Recalcula no resize e a cada mudança de layout.
 *
 * Quando a listagem é o último bloco da página, também recebe `minHeight` igual — ocupa o
 * espaço que sobra em vez de deixar um vazio abaixo do cartão.
 *
 * Diferença do gpe2: devolve uma CALLBACK REF, não um objeto ref. Assim funciona também com
 * tabela que monta depois (aba, carregamento) — o objeto ref só era lido na montagem.
 *
 * Uso: `const ref = useFillViewportHeight(); ... <div ref={ref} className="overflow-auto">`
 * com `<thead className="sticky top-0 ...">` para o cabeçalho ficar fixo.
 *
 * @param {{ gap?: number, minHeight?: number }} [opts]
 * @returns {(el: HTMLElement|null) => void}
 */
export default function useFillViewportHeight({ gap = 56, minHeight = 240 } = {}) {
    const limpar = useRef(null);

    const ref = useCallback((el) => {
        limpar.current?.();
        limpar.current = null;
        if (!el) return;

        // Rodapé FIXO no pé do container: como o cabeçalho sticky no topo, uma faixa sticky
        // embaixo — a linha cortada pela rolagem não encosta na borda do cartão.
        //   - com paginação logo abaixo (irmão), a própria paginação é o rodapé: faixa fina;
        //   - sem paginação, a faixa vira a barra "N registros".
        if (!el.querySelector(':scope > [data-rodape-listagem]')) {
            const rodape = document.createElement('div');
            rodape.dataset.rodapeListagem = '';
            rodape.className = el.nextElementSibling
                ? 'sticky bottom-0 h-2.5 bg-white'
                : 'sticky bottom-0 px-5 py-3 border-t border-gray-100 bg-gray-50/95 text-xs text-gray-500';
            el.appendChild(rodape);
            el.querySelectorAll('tfoot.sticky').forEach((t) => { t.style.bottom = el.nextElementSibling ? '0.625rem' : '2.5rem'; });
        }

        const ajustar = () => {
            // O React pode inserir nós depois da faixa; ela tem de ser o último filho.
            const rodape = el.querySelector(':scope > [data-rodape-listagem]');
            if (rodape && el.lastElementChild !== rodape) el.appendChild(rodape);
            if (rodape && !el.nextElementSibling) {
                // Linha única com colspan é a mensagem de "nenhum registro", não um registro.
                const linhas = [...el.querySelectorAll('tbody > tr')].filter((tr) => !(tr.children.length === 1 && tr.firstElementChild?.colSpan > 1));
                const n = linhas.length;
                const txt = n === 0 ? 'Nenhum registro' : `<strong class="text-gray-700">${n}</strong> registro${n === 1 ? '' : 's'}`;
                if (rodape.innerHTML !== txt) rodape.innerHTML = txt;
            }

            const top = el.getBoundingClientRect().top;
            const irmao = el.nextElementSibling;
            const footers = document.querySelectorAll('footer');
            const footerH = footers.length ? footers[footers.length - 1].offsetHeight : 0;
            let altura = Math.max(minHeight, window.innerHeight - top - (irmao ? irmao.offsetHeight : 0) - footerH - gap);

            // "Última" = nada depois dela nem de nenhum ancestral até o <main>, tolerando o
            // irmão imediato (paginação). Só aí ocupa o espaço que sobra.
            let n = el.nextElementSibling ?? el;
            let ultima = true;
            while (n && n.tagName !== 'MAIN' && n !== document.body) {
                if (n.nextElementSibling) { ultima = false; break; }
                n = n.parentElement;
            }

            el.style.maxHeight = `${altura}px`;
            el.style.minHeight = ultima ? `${altura}px` : '';

            // Correção: o que ainda passa da viewport (conteúdo abaixo que o cálculo não viu).
            const excesso = document.documentElement.scrollHeight - window.innerHeight;
            if (excesso > 0) {
                altura = Math.max(minHeight, altura - excesso);
                el.style.maxHeight = `${altura}px`;
                if (ultima) el.style.minHeight = `${altura}px`;
            }
        };

        ajustar();
        window.addEventListener('resize', ajustar);
        const ro = new ResizeObserver(ajustar);
        ro.observe(document.body);

        limpar.current = () => {
            window.removeEventListener('resize', ajustar);
            ro.disconnect();
        };
    }, [gap, minHeight]);

    useEffect(() => () => limpar.current?.(), []);

    return ref;
}
