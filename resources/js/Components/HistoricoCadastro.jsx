/**
 * Lista do histórico de alterações do cadastro (TR 4.7.22) — usada no Meu
 * perfil e na edição do usuário pelo administrador.
 */
export default function HistoricoCadastro({ historico = [] }) {
    if (historico.length === 0) {
        return <p className="text-sm text-gray-400 text-center py-8">Nenhuma alteração registrada ainda.</p>;
    }
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                        <th className="px-4 py-2.5">Quando</th><th className="px-4 py-2.5">O quê</th>
                        <th className="px-4 py-2.5">Antes</th><th className="px-4 py-2.5">Depois</th><th className="px-4 py-2.5">Quem alterou</th>
                    </tr>
                </thead>
                <tbody>
                    {historico.map((h, i) => (
                        <tr key={i} className="border-b border-gray-50 align-top">
                            <td className="px-4 py-2.5 text-gray-500 whitespace-nowrap">{h.quando}</td>
                            <td className="px-4 py-2.5 font-medium text-gray-800">{h.campo}</td>
                            <td className="px-4 py-2.5 text-gray-400 line-through decoration-gray-300">{h.antes || '—'}</td>
                            <td className="px-4 py-2.5 text-gray-800">{h.depois || '—'}</td>
                            <td className="px-4 py-2.5 text-gray-600">{h.por}{h.ip && <span className="block text-[11px] text-gray-400">IP {h.ip}</span>}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
