import { useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

const URL_FAVORITAR = '/rotinas/favoritos';
const URL_REMOVER = '/rotinas/favoritos/remover';
const ICONE_PADRAO = 'fas fa-circle';

/**
 * Favoritar rotinas — único ponto que fala com o backend (a estrela do cabeçalho e a do
 * Ctrl+K usam este hook). O estado vem do servidor (prop `favoritos`), sem atualização
 * otimista; um clique repetido na mesma rotina enquanto o anterior está em voo é ignorado.
 *
 * @returns {{ ehFavorito: (href: string) => boolean, alternar: (rotina) => void, enviando: string|null }}
 */
export default function useFavoritoRotina() {
    const favoritos = usePage().props.favoritos || [];
    const hrefs = useMemo(() => new Set(favoritos.map((f) => f.href)), [favoritos]);
    const emVoo = useRef(new Set());
    const [enviando, setEnviando] = useState(null);

    const ehFavorito = (href) => hrefs.has(href);

    const alternar = ({ href, label, icon }) => {
        if (emVoo.current.has(href)) return;
        emVoo.current.add(href);
        setEnviando(href);
        const opts = {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => { emVoo.current.delete(href); setEnviando(null); },
        };
        if (hrefs.has(href)) {
            router.post(URL_REMOVER, { href }, opts);
        } else {
            router.post(URL_FAVORITAR, { href, label, icon: icon || ICONE_PADRAO }, opts);
        }
    };

    return { ehFavorito, alternar, enviando };
}
