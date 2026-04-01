/**
 * Componente DataTable
 *
 * Tabela de dados responsiva com busca local, ordenação e paginação.
 * Trabalha com dados vindos do servidor (paginação Laravel).
 *
 * Props:
 *   - columns: Array<{ key, label, sortable?, render?, className? }>
 *   - data: Array de objetos com os dados
 *   - pagination: Objeto de paginação Laravel (links, current_page, etc.)
 *   - searchable: boolean — habilita campo de busca (default: true)
 *   - actions: function(row) — renderiza coluna de ações
 *   - emptyMessage: string — mensagem quando não há dados
 */
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';

export default function DataTable({
    columns = [],
    data = [],
    pagination = null,
    searchable = true,
    actions = null,
    emptyMessage = 'Nenhum registro encontrado.',
    onSearch = null,
}) {
    const [search, setSearch] = useState('');

    const handleSearch = (e) => {
        e.preventDefault();
        if (onSearch) {
            onSearch(search);
        }
    };

    return (
        <div>
            {/* Barra de busca */}
            {searchable && (
                <form onSubmit={handleSearch} className="mb-4 flex flex-col sm:flex-row gap-3">
                    <div className="relative w-full sm:w-80">
                        <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar..."
                            className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        className="px-4 py-2.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Filtrar
                    </button>
                </form>
            )}

            {/* Tabela */}
            <div className="overflow-x-auto rounded-lg border border-gray-200">
                <table className="w-full text-sm text-left">
                    <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
                        <tr>
                            {columns.map((col) => (
                                <th key={col.key} className={`px-4 py-3 font-semibold ${col.className || ''}`}>
                                    {col.label}
                                </th>
                            ))}
                            {actions && <th className="px-4 py-3 font-semibold text-center w-32">Ações</th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {data.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-8 text-center text-gray-400">
                                    <i className="fas fa-inbox text-3xl mb-2 block" />
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            data.map((row, idx) => (
                                <tr key={row.id || idx} className="hover:bg-gray-50 transition-colors">
                                    {columns.map((col) => (
                                        <td key={col.key} className={`px-4 py-3 ${col.className || ''}`}>
                                            {col.render ? col.render(row) : row[col.key]}
                                        </td>
                                    ))}
                                    {actions && (
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex items-center justify-center gap-1">
                                                {actions(row)}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Paginação */}
            {pagination && pagination.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between">
                    <span className="text-sm text-gray-500">
                        Mostrando {pagination.from}-{pagination.to} de {pagination.total} registros
                    </span>
                    <div className="flex gap-1">
                        {pagination.links?.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || '#'}
                                preserveScroll
                                className={`px-3 py-1.5 text-sm rounded-md transition-colors
                                    ${link.active
                                        ? 'bg-blue-600 text-white'
                                        : link.url
                                            ? 'bg-white border text-gray-700 hover:bg-gray-50'
                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
