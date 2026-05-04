/**
 * Admin — Detalhe de uma solicitacao + atendimento.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Card from '../../../../Components/Card';

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

export default function SolicitacaoAdminShow({ solicitacao, processo, statusList }) {
    const formStatus = useForm({
        status: solicitacao.status,
        resposta: solicitacao.resposta || '',
    });
    const formComentario = useForm({ mensagem: '' });
    const [aba, setAba] = useState('atender');

    const submitStatus = (e) => {
        e.preventDefault();
        formStatus.post(`/configuracoes/solicitacoes-portal/${solicitacao.id}/status`, { preserveScroll: true });
    };

    const submitComentario = (e) => {
        e.preventDefault();
        formComentario.post(`/configuracoes/solicitacoes-portal/${solicitacao.id}/comentar`, {
            preserveScroll: true,
            onSuccess: () => formComentario.reset('mensagem'),
        });
    };

    return (
        <AdminLayout>
            <Head title={`${solicitacao.codigo} — Atendimento`} />

            <PageHeader
                title={solicitacao.codigo}
                subtitle={solicitacao.servico?.titulo}
            >
                <Link href="/configuracoes/solicitacoes-portal"
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-semibold">
                    <i className="fas fa-arrow-left text-xs" /> Voltar
                </Link>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2 space-y-4">
                    {/* Cabecalho */}
                    <Card>
                        <div className="flex items-start gap-4 mb-4 pb-4 border-b border-gray-100">
                            <div className="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shrink-0">
                                <i className={`${solicitacao.servico?.icone || 'fas fa-file-alt'} text-xl`} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1 flex-wrap">
                                    <span className={`text-[11px] uppercase tracking-wider px-2.5 py-0.5 rounded-full font-semibold border ${STATUS_CORES[solicitacao.status]}`}>
                                        {statusList[solicitacao.status]}
                                    </span>
                                    {solicitacao.servico?.categoria && (
                                        <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-semibold">
                                            {solicitacao.servico.categoria.nome}
                                        </span>
                                    )}
                                </div>
                                <h2 className="text-lg font-bold text-gray-800">{solicitacao.servico?.titulo}</h2>
                                <p className="text-xs text-gray-500">
                                    Aberta em {new Date(solicitacao.created_at).toLocaleString('pt-BR')}
                                </p>
                            </div>
                        </div>

                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descricao do cidadao</h3>
                        <p className="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-4 leading-relaxed">{solicitacao.descricao}</p>
                    </Card>

                    {/* Tabs Atender / Comentar */}
                    <Card>
                        <div className="flex gap-1 mb-4 border-b border-gray-100">
                            <button onClick={() => setAba('atender')}
                                className={`px-4 py-2 text-sm font-semibold border-b-2 transition-colors
                                    ${aba === 'atender' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                <i className="fas fa-check-circle mr-1.5 text-xs" /> Alterar status
                            </button>
                            <button onClick={() => setAba('comentar')}
                                className={`px-4 py-2 text-sm font-semibold border-b-2 transition-colors
                                    ${aba === 'comentar' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                <i className="fas fa-comment mr-1.5 text-xs" /> Enviar mensagem
                            </button>
                        </div>

                        {aba === 'atender' ? (
                            <form onSubmit={submitStatus} className="space-y-3">
                                <div>
                                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Status</label>
                                    <select value={formStatus.data.status} onChange={(e) => formStatus.setData('status', e.target.value)}
                                        className="w-full px-4 py-2.5 rounded-lg border border-gray-200 text-sm">
                                        {Object.entries(statusList).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-semibold text-gray-600 mb-1 block">
                                        Resposta ao cidadao <span className="text-gray-400 font-normal">(opcional, sera enviada por email)</span>
                                    </label>
                                    <textarea value={formStatus.data.resposta} onChange={(e) => formStatus.setData('resposta', e.target.value)}
                                        rows={5} maxLength={5000}
                                        placeholder="Escreva uma resposta ou orientacao..."
                                        className="w-full px-4 py-3 rounded-lg border border-gray-200 text-sm" />
                                </div>
                                <div className="flex justify-end pt-2">
                                    <button type="submit" disabled={formStatus.processing}
                                        className="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50 text-sm">
                                        {formStatus.processing ? 'Salvando...' : 'Atualizar e notificar cidadao'}
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <form onSubmit={submitComentario} className="space-y-3">
                                <div>
                                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Mensagem</label>
                                    <textarea value={formComentario.data.mensagem} onChange={(e) => formComentario.setData('mensagem', e.target.value)}
                                        rows={4} required maxLength={5000}
                                        placeholder="Escreva uma mensagem para o cidadao (sera enviada por email)..."
                                        className="w-full px-4 py-3 rounded-lg border border-gray-200 text-sm" />
                                </div>
                                <div className="flex justify-end pt-2">
                                    <button type="submit" disabled={formComentario.processing}
                                        className="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50 text-sm">
                                        <i className="fas fa-paper-plane mr-1.5" />
                                        {formComentario.processing ? 'Enviando...' : 'Enviar mensagem'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </Card>

                    {/* Timeline */}
                    <Card>
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
                                                {ev.autor_tipo === 'cidadao' ? 'Cidadao' : ev.autor_tipo === 'atendente' ? 'Atendente' : 'Sistema'}
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
                    </Card>
                </div>

                {/* Sidebar */}
                <aside className="space-y-4">
                    {processo && (
                        <Card>
                            <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <i className="fas fa-project-diagram text-purple-600" />
                                Processo no GPE Flow
                            </h3>
                            <p className="text-[10px] text-gray-400 uppercase tracking-wider">Protocolo</p>
                            <p className="font-mono text-sm font-bold text-gray-800 mb-3">{processo.numero_protocolo}</p>
                            <Link href={`/processos/${processo.id}`}
                                className="block w-full text-center px-3 py-2 rounded-lg bg-purple-50 text-purple-700 text-xs font-semibold hover:bg-purple-100">
                                <i className="fas fa-arrow-right mr-1" /> Abrir no GPE Flow
                            </Link>
                        </Card>
                    )}

                    <Card>
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                            {solicitacao.anonima ? 'Solicitante' : 'Cidadao'}
                        </h3>
                        {solicitacao.anonima ? (
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
                                <i className="fas fa-user-secret text-amber-600 text-2xl mb-1" />
                                <p className="text-sm font-bold text-amber-800">Solicitacao Anonima</p>
                                <p className="text-[11px] text-amber-700 mt-1">A identidade do solicitante nao foi registrada.</p>
                            </div>
                        ) : (
                            <>
                        <div className="flex items-center gap-3 mb-3">
                            <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {(solicitacao.cidadao?.nome || 'C').substring(0, 2).toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold text-gray-800 truncate">{solicitacao.cidadao?.nome}</p>
                                <p className="text-xs text-gray-500 truncate">{solicitacao.cidadao?.email}</p>
                            </div>
                        </div>
                        <dl className="space-y-2 text-xs pt-3 border-t border-gray-100">
                            {solicitacao.cidadao?.cpf && (
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-id-card text-gray-400 w-4 text-center" />
                                    <span className="text-gray-700">{solicitacao.cidadao.cpf}</span>
                                </div>
                            )}
                            {solicitacao.cidadao?.telefone && (
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-phone text-gray-400 w-4 text-center" />
                                    <span className="text-gray-700">{solicitacao.cidadao.telefone}</span>
                                </div>
                            )}
                            {solicitacao.email_contato && solicitacao.email_contato !== solicitacao.cidadao?.email && (
                                <div className="flex items-start gap-2">
                                    <i className="fas fa-envelope text-gray-400 w-4 text-center mt-0.5" />
                                    <div>
                                        <p className="text-[10px] text-gray-400">Email de contato (especifico)</p>
                                        <span className="text-gray-700">{solicitacao.email_contato}</span>
                                    </div>
                                </div>
                            )}
                            {solicitacao.telefone_contato && solicitacao.telefone_contato !== solicitacao.cidadao?.telefone && (
                                <div className="flex items-start gap-2">
                                    <i className="fas fa-phone text-gray-400 w-4 text-center mt-0.5" />
                                    <div>
                                        <p className="text-[10px] text-gray-400">Telefone de contato (especifico)</p>
                                        <span className="text-gray-700">{solicitacao.telefone_contato}</span>
                                    </div>
                                </div>
                            )}
                        </dl>
                            </>
                        )}
                    </Card>
                </aside>
            </div>
        </AdminLayout>
    );
}
