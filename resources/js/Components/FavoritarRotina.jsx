import { usePage } from '@inertiajs/react';
import { rotinaDaUrl } from '../menus';
import useFavoritoRotina from '../Hooks/useFavoritoRotina';

/**
 * Estrela de favoritar a ROTINA atual — fica no cabeçalho da tela (PageHeader).
 *
 * Só aparece quando a tela é uma rotina do menu (detalhe/formulário não são). Usa os
 * mesmos favoritos da busca global (Ctrl+K): favoritar aqui aparece lá, e vice-versa.
 */
export default function FavoritarRotina() {
    const { url } = usePage();
    const { ehFavorito, alternar, enviando } = useFavoritoRotina();
    const rotina = rotinaDaUrl(url);
    if (!rotina) return null;

    const favorito = ehFavorito(rotina.href);
    const emEnvio = enviando === rotina.href;
    const rotulo = favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';

    return (
        <button type="button" onClick={() => alternar(rotina)} disabled={emEnvio}
            title={rotulo} aria-label={rotulo} aria-pressed={favorito}
            className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors
                ${favorito ? 'text-amber-400 hover:bg-amber-50' : 'text-gray-300 hover:text-amber-400 hover:bg-amber-50'}`}>
            <i className={`fas fa-star text-sm ${emEnvio ? 'animate-pulse' : ''}`} />
        </button>
    );
}
