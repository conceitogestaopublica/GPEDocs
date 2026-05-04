/**
 * Portal — Lista de solicitacoes do cidadao.
 */
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

const STATUS_CORES = {
    aberta:         'bg-blue-100 text-blue-700',
    em_atendimento: 'bg-amber-100 text-amber-700',
    atendida:       'bg-green-100 text-green-700',
    recusada:       'bg-red-100 text-red-700',
    cancelada:      'bg-gray-100 text-gray-600',
};

export default function MinhasSolicitacoes({ ug, solicitacoes, statusList }) {
    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title="Minhas Solicitacoes" />

            <h1 className="text-2xl font-bold text-gray-800 mb-1">Minhas Solicitacoes</h1>
            <p className="text-sm text-gray-500 mb-6">Acompanhe o andamento dos servicos solicitados</p>

            {solicitacoes.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                    <i className="fas fa-inbox text-4xl text-gray-300 mb-3" />
                    <p className="text-gray-600 font-medium mb-1">Nenhuma solicitacao registrada</p>
                    <p className="text-xs text-gray-400 mb-4">Voce ainda nao solicitou nenhum servico</p>
                    <Link href="/buscar" className="inline-block px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                        Ver servicos disponiveis
                    </Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {solicitacoes.map(sol => (
                        <Link
                            key={sol.id}
                            href={`/minhas-solicitacoes/${sol.id}`}
                            className="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 transition-all flex items-center gap-4 group"
                        >
                            <div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                                <i className={`${sol.servico?.icone || 'fas fa-file-alt'} text-lg`} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1 flex-wrap">
                                    <p className="text-[10px] font-mono text-gray-400 uppercase tracking-wider">{sol.codigo}</p>
                                    <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold ${STATUS_CORES[sol.status]}`}>
                                        {statusList[sol.status]}
                                    </span>
                                </div>
                                <p className="text-sm font-semibold text-gray-800 group-hover:text-blue-700">{sol.servico?.titulo}</p>
                                <p className="text-xs text-gray-500 line-clamp-1 mt-0.5">{sol.descricao}</p>
                            </div>
                            <div className="text-right shrink-0">
                                <p className="text-[10px] text-gray-400 uppercase tracking-wider">Aberta em</p>
                                <p className="text-xs font-semibold text-gray-700">
                                    {new Date(sol.created_at).toLocaleDateString('pt-BR')}
                                </p>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </PortalLayout>
    );
}
