/**
 * Portal — Formulario de solicitacao de servico.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

export default function PortalSolicitar({ ug, servico, cidadao }) {
    const { data, setData, post, processing, errors } = useForm({
        descricao: '',
        telefone_contato: cidadao?.telefone || '',
        email_contato: cidadao?.email || '',
        anonima: ! cidadao && servico.permite_anonimo, // padrao anonimo se nao logado
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/servico/${servico.slug}/solicitar`);
    };

    const isAnonimo = !!data.anonima;

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`Solicitar ${servico.titulo}`} />

            <nav className="text-xs text-gray-500 mb-4">
                <Link href="/" className="hover:text-blue-600">Inicio</Link>
                <i className="fas fa-chevron-right mx-2 text-[10px]" />
                <Link href={`/servico/${servico.slug}`} className="hover:text-blue-600">{servico.titulo}</Link>
                <i className="fas fa-chevron-right mx-2 text-[10px]" />
                <span className="text-gray-700 font-medium">Solicitar</span>
            </nav>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <form onSubmit={submit} className="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6 lg:p-8 space-y-5">
                    <div className="flex items-start gap-4 pb-4 border-b border-gray-100">
                        <div className="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center shrink-0">
                            <i className={`${servico.icone || 'fas fa-file-alt'} text-xl`} />
                        </div>
                        <div>
                            <p className="text-[11px] uppercase tracking-wider text-blue-600 font-bold">Solicitando</p>
                            <h1 className="text-xl font-bold text-gray-800">{servico.titulo}</h1>
                            <p className="text-xs text-gray-500 mt-1">{servico.descricao_curta}</p>
                        </div>
                    </div>

                    {servico.permite_anonimo && (
                        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" checked={isAnonimo}
                                    onChange={(e) => setData('anonima', e.target.checked)}
                                    className="mt-0.5" />
                                <div>
                                    <p className="text-sm font-bold text-amber-900">Solicitar anonimamente</p>
                                    <p className="text-[11px] text-amber-800">
                                        Sua identidade nao sera registrada. Voce nao recebera atualizacoes por email,
                                        mas anote o codigo da solicitacao para acompanhamento futuro.
                                    </p>
                                </div>
                            </label>
                        </div>
                    )}

                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Descreva sua solicitacao *</label>
                        <textarea value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                            rows={6} required maxLength={5000}
                            placeholder="Detalhe o que voce precisa, dados relevantes (numero de inscricao, endereco, etc) e qualquer observacao..."
                            className="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        {errors.descricao && <p className="text-xs text-red-500 mt-1">{errors.descricao}</p>}
                        <p className="text-[10px] text-gray-400 mt-1">{data.descricao.length} / 5000</p>
                    </div>

                    {!isAnonimo && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-4 border-t border-gray-100">
                            <div>
                                <label className="text-xs font-semibold text-gray-600 mb-1 block">E-mail para contato</label>
                                <input type="email" value={data.email_contato} onChange={(e) => setData('email_contato', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm" />
                                {errors.email_contato && <p className="text-xs text-red-500 mt-1">{errors.email_contato}</p>}
                            </div>
                            <div>
                                <label className="text-xs font-semibold text-gray-600 mb-1 block">Telefone para contato</label>
                                <input type="text" value={data.telefone_contato} onChange={(e) => setData('telefone_contato', e.target.value)}
                                    placeholder="(00) 00000-0000"
                                    className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm" />
                            </div>
                        </div>
                    )}

                    <div className="flex gap-2 pt-4 border-t border-gray-100">
                        <Link href={`/servico/${servico.slug}`}
                            className="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-semibold">
                            Cancelar
                        </Link>
                        <button type="submit" disabled={processing}
                            className="flex-1 px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50 transition-colors">
                            <i className="fas fa-paper-plane mr-2" />
                            {processing ? 'Enviando...' : 'Enviar solicitacao'}
                        </button>
                    </div>
                </form>

                <aside className="space-y-4">
                    <div className="bg-blue-50 border border-blue-200 rounded-2xl p-5">
                        <h3 className="text-sm font-bold text-blue-900 mb-2 flex items-center gap-2">
                            <i className="fas fa-info-circle" />
                            Como funciona
                        </h3>
                        <ol className="text-xs text-blue-800 space-y-2 list-decimal list-inside">
                            <li>Voce envia a solicitacao com a descricao</li>
                            <li>Sua solicitacao recebe um codigo de acompanhamento</li>
                            <li>O setor responsavel recebe e analisa</li>
                            <li>Voce recebe notificacao por e-mail a cada atualizacao</li>
                        </ol>
                    </div>

                    {servico.documentos_necessarios && servico.documentos_necessarios.length > 0 && (
                        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                            <h3 className="text-sm font-bold text-amber-900 mb-2 flex items-center gap-2">
                                <i className="fas fa-folder" />
                                Tenha em maos
                            </h3>
                            <ul className="text-xs text-amber-800 space-y-1.5">
                                {servico.documentos_necessarios.map((d, i) => (
                                    <li key={i} className="flex items-start gap-2">
                                        <i className="fas fa-check-circle text-amber-600 mt-0.5 shrink-0" />
                                        <span>{d}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </aside>
            </div>
        </PortalLayout>
    );
}
