/**
 * Lista de sugestões do editor (variáveis, pessoas, documentos). Setas escolhem, Enter/Tab insere.
 * `items` pode chegar como promessa (busca no servidor).
 */
import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';

const ListaSugestoes = forwardRef(function ListaSugestoes({ items, command }, ref) {
    const [lista, setLista] = useState(Array.isArray(items) ? items : []);
    const [ativo, setAtivo] = useState(0);
    const [carregando, setCarregando] = useState(false);

    useEffect(() => {
        let vivo = true;
        setAtivo(0);
        if (Array.isArray(items)) { setLista(items); return undefined; }
        setCarregando(true);
        Promise.resolve(items).then((r) => { if (vivo) { setLista(r || []); setCarregando(false); } });
        return () => { vivo = false; };
    }, [items]);

    const escolher = (i) => { const item = lista[i]; if (item) command({ id: item.id, label: item.label }); };

    useImperativeHandle(ref, () => ({
        onKeyDown: ({ event }) => {
            if (event.key === 'ArrowDown') { setAtivo((a) => (a + 1) % Math.max(lista.length, 1)); return true; }
            if (event.key === 'ArrowUp') { setAtivo((a) => (a - 1 + lista.length) % Math.max(lista.length, 1)); return true; }
            if (event.key === 'Enter' || event.key === 'Tab') { escolher(ativo); return lista.length > 0; }
            return false;
        },
    }));

    return (
        <div className="w-80 max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-sm">
            {carregando && <p className="px-3 py-2 text-xs text-gray-400">Buscando…</p>}
            {! carregando && lista.length === 0 && <p className="px-3 py-2 text-xs text-gray-400">Nada encontrado.</p>}
            {lista.map((item, i) => (
                <button key={item.id} type="button" onMouseDown={(e) => { e.preventDefault(); escolher(i); }}
                    className={`block w-full text-left px-3 py-1.5 ${i === ativo ? 'bg-cyan-50 text-cyan-900' : 'text-gray-700 hover:bg-gray-50'}`}>
                    <span className="font-medium">{item.label}</span>
                    {item.detalhe && <span className="block text-[11px] text-gray-500 truncate">{item.detalhe}</span>}
                </button>
            ))}
        </div>
    );
});

export default ListaSugestoes;
