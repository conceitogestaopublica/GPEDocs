import { useState } from 'react';
import Card from './Card';

const data = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

/**
 * Quem abriu o documento (TR 4.14.2–4.14.4): servidores e externos, com o primeiro e o
 * último acesso e quantas vezes. Mostra os 5 mais recentes; o resto ao expandir.
 */
export default function QuemAcessou({ acessos = [], className = 'mt-6' }) {
    const [todos, setTodos] = useState(false);
    if (! acessos.length) return null;
    const lista = todos ? acessos : acessos.slice(0, 5);

    return (
        <Card title={`Quem acessou (${acessos.length})`} className={className}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                            <th className="py-2 pr-4 font-medium">Pessoa</th>
                            <th className="py-2 pr-4 font-medium">Primeiro acesso</th>
                            <th className="py-2 pr-4 font-medium">Último acesso</th>
                            <th className="py-2 font-medium text-right">Vezes</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {lista.map((a, i) => (
                            <tr key={i}>
                                <td className="py-2 pr-4">
                                    <span className="font-medium text-gray-800">{a.nome || '-'}</span>
                                    {a.externo
                                        ? <span className="ml-2 text-[10px] font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-full px-2 py-0.5">externo</span>
                                        : a.setor && <span className="block text-xs text-gray-500">{a.setor}</span>}
                                </td>
                                <td className="py-2 pr-4 text-gray-600 whitespace-nowrap">{data(a.primeiro_em)}</td>
                                <td className="py-2 pr-4 text-gray-600 whitespace-nowrap">{data(a.ultimo_em)}</td>
                                <td className="py-2 text-right text-gray-600">{a.vezes}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {acessos.length > 5 && (
                <button type="button" onClick={() => setTodos(! todos)} className="mt-2 text-xs text-cyan-700 hover:underline">
                    {todos ? 'Mostrar menos' : `Ver todos os ${acessos.length}`}
                </button>
            )}
        </Card>
    );
}
