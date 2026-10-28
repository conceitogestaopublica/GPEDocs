/**
 * Lista de servidores para marcar como signatários, com busca por nome ou
 * e-mail (sem diferenciar acento e maiúscula). Os já marcados continuam
 * visíveis no topo mesmo quando a busca não os encontra.
 */
import { useMemo, useState } from 'react';

const normalizar = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default function ListaSignatarios({ usuarios = [], selecionados = [], onToggle, mostrarOrdem = false, alturaMaxima = 'max-h-56' }) {
    const [busca, setBusca] = useState('');

    const visiveis = useMemo(() => {
        const termo = normalizar(busca.trim());
        const confere = (u) => !termo || normalizar(u.name).includes(termo) || normalizar(u.email).includes(termo);
        // Marcados na ordem em que foram marcados (é a ordem da assinatura sequencial)
        const marcados = selecionados.map(id => usuarios.find(u => u.id === id)).filter(Boolean);
        const outros = usuarios.filter(u => !selecionados.includes(u.id) && confere(u));
        return [...marcados, ...outros];
    }, [usuarios, selecionados, busca]);

    return (
        <div className="border border-gray-200 rounded-xl bg-white">
            <div className="flex items-center gap-2 p-2 border-b border-gray-100">
                <div className="relative flex-1">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                    <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter') e.preventDefault(); }}
                        placeholder="Buscar servidor por nome ou e-mail..." className="ds-input pl-9" />
                </div>
                {selecionados.length > 0 && (
                    <span className="text-xs text-blue-700 bg-blue-50 rounded-full px-2.5 py-1 whitespace-nowrap">
                        {selecionados.length} selecionado(s)
                    </span>
                )}
            </div>
            <div className={`${alturaMaxima} overflow-y-auto p-2 space-y-1`}>
                {visiveis.map(u => {
                    const marcado = selecionados.includes(u.id);
                    return (
                        <label key={u.id} className={`flex items-center gap-2 text-sm cursor-pointer rounded px-2 py-1.5 ${marcado ? 'bg-blue-50' : 'hover:bg-gray-50'}`}>
                            <input type="checkbox" checked={marcado} onChange={() => onToggle(u.id)}
                                className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                            <span className="text-gray-700">{u.name}</span>
                            <span className="text-xs text-gray-400 ml-auto truncate">{u.email}</span>
                            {mostrarOrdem && marcado && (
                                <span className="text-[10px] font-bold text-blue-700 bg-blue-100 rounded px-1.5">
                                    {selecionados.indexOf(u.id) + 1}º
                                </span>
                            )}
                        </label>
                    );
                })}
                {visiveis.length === 0 && (
                    <p className="text-xs text-gray-400 text-center py-3">
                        {usuarios.length === 0 ? 'Nenhum usuário disponível' : `Nenhum servidor encontrado para "${busca}"`}
                    </p>
                )}
            </div>
        </div>
    );
}
