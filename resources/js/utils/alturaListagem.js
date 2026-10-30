/**
 * Conta da altura de uma listagem que rola por dentro (useFillViewportHeight) — funções
 * puras, sem DOM, para dar para testar.
 */

/** Altura que sobra para a listagem: da posição dela até o fim da tela, menos o que vem abaixo. */
export function alturaDisponivel({ viewport, topo, irmao = 0, rodapeLayout = 0, folga, minima }) {
    return Math.max(minima, viewport - topo - irmao - rodapeLayout - folga);
}

/** Se a página ainda passa da tela (conteúdo abaixo que a conta não viu), desconta o excesso. */
export function semExcesso(altura, excesso, minima) {
    return excesso > 0 ? Math.max(minima, altura - excesso) : altura;
}
