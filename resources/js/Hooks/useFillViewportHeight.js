import { useCallback, useEffect, useRef } from 'react';
import { alturaDisponivel, semExcesso } from '../utils/alturaListagem';

/** Paddings dos containers acima/abaixo da listagem + respiro. */
const FOLGA_PADRAO = 56;
/** Abaixo disto a listagem não encolhe (tela baixa): aí a página rola, a lista continua usável. */
const ALTURA_MINIMA = 240;

/**
 * Faz o container de uma LISTAGEM preencher a altura que sobra até o fim da tela, rolando
 * SÓ por dentro — a página inteira não rola (padrão gpe2). Só MEDE e aplica a altura: o
 * rodapé "N registros" e o cabeçalho fixo são do componente <TabelaRolavel>, renderizados
 * pelo React (antes o hook inseria/reescrevia nós dentro do DOM do React).
 *
 * Conta (utils/alturaListagem): do topo do elemento até o fim da tela, menos o irmão logo
 * abaixo (paginação), o <footer> do layout e uma folga; depois desconta o que ainda passar
 * da tela. Sendo o último bloco da página, também ocupa o espaço que sobra (minHeight).
 * Recalcula no resize e a cada mudança de layout.
 *
 * Devolve uma CALLBACK REF (funciona com tabela que monta depois — aba, carregamento).
 */
export default function useFillViewportHeight({ folga = FOLGA_PADRAO, minima = ALTURA_MINIMA } = {}) {
    const limpar = useRef(null);

    const ref = useCallback((el) => {
        limpar.current?.();
        limpar.current = null;
        if (!el) return;

        const ajustar = () => {
            const irmao = el.nextElementSibling;
            const footers = document.querySelectorAll('footer');
            let altura = alturaDisponivel({
                viewport: window.innerHeight,
                topo: el.getBoundingClientRect().top,
                irmao: irmao ? irmao.offsetHeight : 0,
                rodapeLayout: footers.length ? footers[footers.length - 1].offsetHeight : 0,
                folga,
                minima,
            });
            const ultima = ehUltimoBloco(el);

            el.style.maxHeight = `${altura}px`;
            el.style.minHeight = ultima ? `${altura}px` : '';

            altura = semExcesso(altura, document.documentElement.scrollHeight - window.innerHeight, minima);
            el.style.maxHeight = `${altura}px`;
            if (ultima) el.style.minHeight = `${altura}px`;
        };

        ajustar();
        window.addEventListener('resize', ajustar);
        const ro = new ResizeObserver(ajustar);
        ro.observe(document.body);

        limpar.current = () => {
            window.removeEventListener('resize', ajustar);
            ro.disconnect();
        };
    }, [folga, minima]);

    useEffect(() => () => limpar.current?.(), []);

    return ref;
}

/**
 * "Último bloco" = nada depois dele nem de nenhum ancestral até o <main>, tolerando o irmão
 * imediato (paginação). Só aí a listagem ocupa o espaço que sobra.
 */
function ehUltimoBloco(el) {
    for (let n = el.nextElementSibling ?? el; n && n.tagName !== 'MAIN' && n !== document.body; n = n.parentElement) {
        if (n.nextElementSibling) return false;
    }
    return true;
}
