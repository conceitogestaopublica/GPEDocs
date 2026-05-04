/**
 * Portal — Detalhe de uma solicitacao do cidadao.
 */
import { Head, Link, router } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

const STATUS_CORES = {
    aberta:         'bg-blue-100 text-blue-700 border-blue-200',
    em_atendimento: 'bg-amber-100 text-amber-700 border-amber-200',
    atendida:       'bg-green-100 text-green-700 border-green-200',
    recusada:       'bg-red-100 text-red-700 border-red-200',
    cancelada:      'bg-gray-100 text-gray-600 border-gray-200',
};

const TIPO_ICONE = {
    criada:           'fas fa-flag',
    status_alterado:  'fas fa-exchange-alt',
    comentario:       'fas fa-comment',
    atendida:         'fas fa-check-circle',
    recusada:         'fas fa-times-circle',
    cancelada:        'fas fa-ban',
};

const STATUS_FINAIS = ['atendida', 'recusada', 'cancelada'];

export default function SolicitacaoShow({ ug, solicitacao, anexos = [], statusList }) {
    const cancelar = () => {
        if (! confirm('Cancelar esta solicitacao? Esta acao nao pode ser desfeita.')) return;
        router.post(`/minhas-solicitacoes/${solicitacao.id}/cancelar`);
    };

    const podeCancelar = ! STATUS_FINAIS.includes(solicitacao.status);

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`${solicitacao.codigo} — ${solicitacao.servico?.titulo}`} />

            <nav className="text-xs text-gray-500 mb-4">
                <Link href="/minhas-solicitacoes" className="hover:text-blue-600">Minhas Solicitacoes</Link>
                <i className="fas fa-chevron-right mx-2 text-[10px]" />
                <span className="text-gray-700 font-medium font-mono">{solicitacao.codigo}</span>
            </nav>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-4">
                    {/* Cabecalho */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-6">
                        <div className="flex items-start gap-4 mb-4 pb-4 border-b border-gray-100">
                            <div className="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shrink-0">
                                <i className={`${solicitacao.servico?.icone || 'fas fa-file-alt'} text-xl`} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1 flex-wrap">
                                    <p className="text-[10px] font-mono text-gray-400 uppercase tracking-wider">{solicitacao.codigo}</p>
                                    <span className={`text-[11px] uppercase tracking-wider px-2.5 py-0.5 rounded-full font-semibold border ${STATUS_CORES[solicitacao.status]}`}>
                                        {statusList[solicitacao.status]}
                                    </span>
                                </div>
                                <h1 className="text-xl font-bold text-gray-800">{solicitacao.servico?.titulo}</h1>
                                <p className="text-xs text-gray-500 mt-1">
                                    Aberta em {new Date(solicitacao.created_at).toLocaleString('pt-BR')}
                                </p>
                            </div>
                        </div>

                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Sua descricao</h3>
                        <p className="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-4 leading-relaxed">{solicitacao.descricao}</p>
                    </div>

                    {/* Resposta do atendente */}
                    {(solicitacao.resposta || anexos.length > 0) && (
                        <div className="bg-white rounded-2xl border border-gray-200 p-6">
                            <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i className="fas fa-reply text-blue-600" />
                                Resposta da prefeitura
                            </h3>
                            {solicitacao.resposta && (
                                <div className="bg-blue-50 border-l-4 border-blue-600 rounded-r-lg p-4">
                                    <p className="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{solicitacao.resposta}</p>
                                    {solicitacao.atendente && (
                                        <p className="text-[11px] text-blue-700 mt-3">
                                            — {solicitacao.atendente.name}
                                            {solicitacao.respondida_em && (
                                                <span className="text-gray-500 ml-2">
                                                    em {new Date(solicitacao.respondida_em).toLocaleString('pt-BR')}
                                                </span>
                                            )}
                                        </p>
                                    )}
                                </div>
                            )}

                            {anexos.length > 0 && (
                                <div className="mt-4 pt-4 border-t border-gray-100">
                                    <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                        <i className="fas fa-paperclip mr-1" /> Documentos anexados ({anexos.length})
                                    </p>
                                    <div className="space-y-2">
                                        {anexos.map(anx => (
                                            <a key={anx.id} href={`/anexo/${anx.id}`} target="_blank" rel="noreferrer"
                                                className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors group">
                                                <div className="w-10 h-10 bg-blue-50 group-hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                                    <i className={`fas ${anx.mime_type?.startsWith('image/') ? 'fa-image' : anx.mime_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file'} text-lg`} />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-semibold text-gray-800 truncate">{anx.nome}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {anx.tamanho ? `${(anx.tamanho / 1024).toFixed(0)} KB` : ''}
                                                        {anx.created_at && ` · ${new Date(anx.created_at).toLocaleDateString('pt-BR')}`}
                                                    </p>
                                                </div>
                                                <i className="fas fa-download text-gray-400 group-hover:text-blue-600" />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Timeline de eventos */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-6">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Linha do tempo</h3>
                        <div className="space-y-4">
                            {(solicitacao.eventos || []).map((ev, i) => (
                                <div key={ev.id} className="flex gap-3">
                                    <div className="flex flex-col items-center shrink-0">
                                        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs ${
                                            ev.autor_tipo === 'cidadao' ? 'bg-blue-100 text-blue-600' :
                                            ev.autor_tipo === 'atendente' ? 'bg-purple-100 text-purple-600' :
                                            'bg-gray-100 text-gray-500'
                                        }`}>
                                            <i className={TIPO_ICONE[ev.tipo] || 'fas fa-circle'} />
                                        </div>
                                        {i < solicitacao.eventos.length - 1 && (
                                            <div className="flex-1 w-0.5 bg-gray-100 my-1" style={{ minHeight: '20px' }} />
                                        )}
                                    </div>
                                    <div className="flex-1 pb-2">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="text-sm font-semibold text-gray-800">{ev.autor_nome}</p>
                                            <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">
                                                {ev.autor_tipo === 'cidadao' ? 'Voce' : ev.autor_tipo === 'atendente' ? 'Atendente' : 'Sistema'}
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-gray-400">{new Date(ev.created_at).toLocaleString('pt-BR')}</p>
                                        {ev.status_novo && ev.status_anterior && (
                                            <p className="text-xs text-gray-600 mt-1">
                                                Status: <span className="font-medium">{statusList[ev.status_anterior]}</span> →{' '}
                                                <span className="font-bold text-blue-700">{statusList[ev.status_novo]}</span>
                                            </p>
                                        )}
                                        {ev.mensagem && (
                                            <p className="text-sm text-gray-700 mt-1 bg-gray-50 rounded-lg p-3 whitespace-pre-line">{ev.mensagem}</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <aside className="space-y-4">
                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Detalhes</h3>
                        <dl className="space-y-2 text-sm">
                            <div>
                                <dt className="text-[10px] uppercase tracking-wider text-gray-400">Servico</dt>
                                <dd className="font-semibold text-gray-800">
                                    <Link href={`/servico/${solicitacao.servico?.slug}`} className="hover:text-blue-600">
                                        {solicitacao.servico?.titulo}
                                    </Link>
                                </dd>
                            </div>
                            {solicitacao.email_contato && (
                                <div>
                                    <dt className="text-[10px] uppercase tracking-wider text-gray-400">E-mail de contato</dt>
                                    <dd className="text-gray-700 text-xs">{solicitacao.email_contato}</dd>
                                </div>
                            )}
                            {solicitacao.telefone_contato && (
                                <div>
                                    <dt className="text-[10px] uppercase tracking-wider text-gray-400">Telefone de contato</dt>
                                    <dd className="text-gray-700 text-xs">{solicitacao.telefone_contato}</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {podeCancelar && (
                        <div className="bg-red-50 border border-red-200 rounded-2xl p-5">
                            <h3 className="text-sm font-bold text-red-800 mb-1">Cancelar solicitacao</h3>
                            <p className="text-xs text-red-700 mb-3">Use apenas se nao precisar mais deste servico.</p>
                            <button onClick={cancelar}
                                className="w-full px-4 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700">
                                <i className="fas fa-ban mr-1" /> Cancelar
                            </button>
                        </div>
                    )}
                </aside>
            </div>
        </PortalLayout>
    );
}
