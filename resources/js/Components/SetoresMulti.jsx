/**
 * Vários setores do organograma: escolhe num select (com recuo pela hierarquia) e
 * mostra como etiquetas removíveis. `unidades`: [{ id, nome, nivel, ug? }].
 */
export default function SetoresMulti({ titulo, ajuda, unidades, valor, onChange, excluir = [], pequeno = false }) {
    const fora = new Set([...valor, ...excluir].filter(Boolean).map(Number));
    const nome = (id) => {
        const u = unidades.find(x => Number(x.id) === Number(id));
        return u ? (u.ug ? `${u.nome} (${u.ug})` : u.nome) : `#${id}`;
    };
    return (
        <div>
            {titulo && (
                <label className={`block font-medium mb-1 ${pequeno ? 'text-[11px] text-gray-500' : 'text-sm text-gray-700'}`}>
                    {titulo} <span className="text-gray-400 text-xs">(opcional)</span>
                </label>
            )}
            <select value="" className={`ds-input ${pequeno ? 'text-xs' : ''}`}
                onChange={(e) => e.target.value && onChange([...valor, Number(e.target.value)])}>
                <option value="">Adicionar setor…</option>
                {unidades.filter(u => ! fora.has(Number(u.id))).map(u => (
                    <option key={u.id} value={u.id}>
                        {' '.repeat(((u.nivel || 1) - 1) * 3)}{u.nome}{u.ug && u.nivel === 1 ? ` (${u.ug})` : ''}
                    </option>
                ))}
            </select>
            {valor.length > 0 && (
                <div className="flex flex-wrap gap-1.5 mt-2">
                    {valor.map(id => (
                        <span key={id} className="inline-flex items-center gap-1.5 text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-full px-2.5 py-1">
                            {nome(id)}
                            <button type="button" onClick={() => onChange(valor.filter(x => x !== id))} className="text-blue-500 hover:text-red-600" title="Remover">
                                <i className="fas fa-times text-[10px]" />
                            </button>
                        </span>
                    ))}
                </div>
            )}
            {ajuda && <p className="mt-1 text-[10px] text-gray-400">{ajuda}</p>}
        </div>
    );
}
