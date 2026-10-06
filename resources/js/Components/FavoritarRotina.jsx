import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { rotinaDaUrl } from '../menus';

/**
 * Estrela de favoritar a ROTINA atual — fica no cabeçalho da tela (PageHeader).
 *
 * Só aparece quando a tela é uma rotina do menu (detalhe/formulário não são). Usa os
 * mesmos favoritos da busca global (Ctrl+K): favoritar aqui aparece lá, e vice-versa.
 */
export default function FavoritarRotina() {
    const { url, props } = usePage();
    const [enviando, setEnviando] = useState(false);
    const rotina = rotinaDaUrl(url);
    if (!rotina) return null;

    const favorito = (props.favoritos || []).some((f) => f.href === rotina.href);

    const alternar = () => {
        if (enviando) return;
        setEnviando(true);
        const opts = { preserveState: true, preserveScroll: true, onFinish: () => setEnviando(false) };
        if (favorito) {
            router.post('/rotinas/favoritos/remover', { href: rotina.href }, opts);
        } else {
            router.post('/rotinas/favoritos', { href: rotina.href, label: rotina.label, icon: rotina.icon || 'fas fa-circle' }, opts);
        }
    };

    const rotulo = favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';

    return (
        <button type="button" onClick={alternar} disabled={enviando}
            title={rotulo} aria-label={rotulo} aria-pressed={favorito}
            className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors
                ${favorito ? 'text-amber-400 hover:bg-amber-50' : 'text-gray-300 hover:text-amber-400 hover:bg-amber-50'}`}>
            <i className={`fas fa-star text-sm ${enviando ? 'animate-pulse' : ''}`} />
        </button>
    );
}
