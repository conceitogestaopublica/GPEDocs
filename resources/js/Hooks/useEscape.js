import { useEffect, useRef } from 'react';

/**
 * Esc fecha SÓ a camada de cima (diálogo sobre modal sobre paleta…).
 *
 * Cada camada aberta entra numa pilha; um único listener, na fase de captura, entrega o Esc
 * à camada do topo e o consome. Antes cada uma ouvia o window por conta própria: um Esc num
 * confirmar() aberto de dentro de um Modal fechava os dois — e o formulário do modal sumia.
 */
const pilha = [];
let instalado = false;

function aoTeclar(e) {
    if (e.key !== 'Escape' || pilha.length === 0) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    pilha[pilha.length - 1].current?.();
}

export default function useEscape(onEscape, ativo = true) {
    const ref = useRef(onEscape);
    ref.current = onEscape;

    useEffect(() => {
        if (!ativo) return undefined;
        if (!instalado) {
            document.addEventListener('keydown', aoTeclar, true);
            instalado = true;
        }
        pilha.push(ref);
        return () => {
            const i = pilha.lastIndexOf(ref);
            if (i >= 0) pilha.splice(i, 1);
        };
    }, [ativo]);
}
