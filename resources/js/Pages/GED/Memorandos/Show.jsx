/**
 * Detalhe do Memorando — GED
 *
 * Visualizacao completa com respostas em thread.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function MemorandosShow({ memorando, pode_receber, pode_tramitar, meu_status, unidades = [], usuarios = [] }) {
    const memo = memorando || {};
    const respostas = memo.respostas || [];
    const destinatarios = memo.destinatarios || [];
    const anexos = memo.anexos || [];
    const tramitacoes = memo.tramitacoes || [];
    const replyRef = useRef(null);
    const [tramitarOpen, setTramitarOpen] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        conteudo: '',
    });

    // Modal de tramitar
    const tramiteForm = useForm({
        tipo_destino: 'setor',
        destino_unidade_id: '',
        destino_usuario_id: '',
        parecer: '',
        registrar_como_resposta: false,
    });

    const unidadesTree = useMemo(() => {
        const filhosPor = new Map();
        unidades.forEach(u => {
            const pid = u.parent_id ?? null;
            if (! filhosPor.has(pid)) filhosPor.set(pid, []);
            filhosPor.get(pid).push(u);
        });
        for (const [, lista] of filhosPor) lista.sort((a, b) => a.nome.localeCompare(b.nome));
        const out = [];
        const visitar = (parentId) => {
            for (const u of (filhosPor.get(parentId) || [])) {
                out.push(u);
                visitar(u.id);
            }
        };
        visitar(null);
        return out;
    }, [unidades]);

    const usuariosFiltrados = useMemo(() => {
        if (! tramiteForm.data.destino_unidade_id) return usuarios;
        return usuarios.filter(u => Number(u.unidade_id) === Number(tramiteForm.data.destino_unidade_id));
    }, [usuarios, tramiteForm.data.destino_unidade_id]);

    const enviarReceber = () => {
        if (confirm('Confirmar recebimento deste memorando?'))
            router.post(`/memorandos/${memo.id}/receber`, {}, { preserveScroll: true });
    };

    const enviarTramitar = (e) => {
        e.preventDefault();
        tramiteForm.post(`/memorandos/${memo.id}/tramitar`, {
            preserveScroll: true,
            onSuccess: () => { tramiteForm.reset(); setTramitarOpen(false); },
        });
    };

    const submitReply = (e) => {
        e.preventDefault();
        post(`/memorandos/${memo.id}/responder`, {
            onSuccess: () => reset(),
            preserveScroll: true,
        });
    };

    const scrollToReply = () => {
        replyRef.current?.scrollIntoView({ behavior: 'smooth' });
        replyRef.current?.querySelector('textarea')?.focus();
    };

    const statusBanner = (() => {
        if (! meu_status) return null;
        const map = {
            remetente: { cor: 'blue', icone: 'fa-paper-plane' },
            pendente:  { cor: 'amber', icone: 'fa-clock' },
            recebido:  { cor: 'emerald', icone: 'fa-check-circle' },
            tramitou:  { cor: 'purple', icone: 'fa-share' },
        };
        const cfg = map[meu_status.estado] || map.recebido;
        const corBg = { blue: 'bg-blue-50 border-blue-200 text-blue-800', amber: 'bg-amber-50 border-amber-200 text-amber-800', emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800', purple: 'bg-purple-50 border-purple-200 text-purple-800' }[cfg.cor];
        return (
            <div className={`rounded-xl border p-3 mb-4 flex items-start gap-3 ${corBg}`}>
                <i className={`fas ${cfg.icone} mt-0.5`} />
                <p className="text-sm">{meu_status.mensagem}</p>
            </div>
        );
    })();

    return (
        <AdminLayout>
            <Head title={`Memorando ${memo.numero || ''}`} />

            {statusBanner}

            {/* Cabecalho */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <h1 className="text-2xl font-bold text-gray-800">{memo.numero}</h1>
                            {memo.status === 'arquivado' && (
                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                    <i className="fas fa-archive mr-0.5" />Arquivado
                                </span>
                            )}
                            {memo.confidencial && (
                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">
                                    <i className="fas fa-lock mr-0.5" />Confidencial
                                </span>
                            )}
                        </div>
                        <h2 className="text-lg text-gray-600">{memo.assunto}</h2>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* QR Code */}
                        {memo.qr_code_token && (
                            <div className="relative group">
                                <button className="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                                    title="QR Code de verificacao">
                                    <i className="fas fa-qrcode" />
                                </button>
                                <div className="absolute right-0 top-12 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-50 hidden group-hover:block animate-fadeIn">
                                    <QRCodeSVG value={`${window.location.origin}/verificar/${memo.qr_code_token}`} size={160} />
                                    <p className="text-[10px] text-gray-400 text-center mt-2">Escaneie para verificar</p>
                                </div>
                            </div>
                        )}

                        {pode_receber && (
                            <Button icon="fas fa-inbox" onClick={enviarReceber}
                                title="Confirmar recebimento (registra timestamp e libera tramitar/responder)">
                                Receber
                            </Button>
                        )}
                        {pode_tramitar && (
                            <Button icon="fas fa-share" onClick={() => setTramitarOpen(true)}
                                title="Encaminhar para outro setor ou usuario (com parecer opcional)">
                                Encaminhar
                            </Button>
                        )}
                        {memo.status !== 'arquivado' && (
                            <Button variant="secondary" icon="fas fa-archive"
                                onClick={() => {
                                    if (confirm('Arquivar este memorando?'))
                                        router.post(`/memorandos/${memo.id}/arquivar`);
                                }}>
                                Arquivar
                            </Button>
                        )}
                        <a href={`/memorandos/${memo.id}/pdf`} target="_blank" rel="noopener noreferrer"
                            className="ds-btn ds-btn-outline">
                            <i className="fas fa-file-pdf mr-1" /> PDF
                        </a>
                        <Button icon="fas fa-reply" onClick={scrollToReply}>Responder</Button>
                        <Button variant="secondary" icon="fas fa-arrow-left" href="/memorandos">Voltar</Button>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Coluna principal */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Conteudo */}
                    <Card title="Conteudo">
                        <div className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                            {memo.conteudo}
                        </div>
                    </Card>

                    {/* Anexos */}
                    {anexos.length > 0 && (
                        <Card title={`Anexos (${anexos.length})`}>
                            <div className="space-y-2">
                                {anexos.map(anexo => (
                                    <a key={anexo.id}
                                        href={`/memorandos/${memo.id}/anexos/${anexo.id}/download`}
                                        className="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition-colors group">
                                        <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                                            <i className={`${getFileIcon(anexo.mime_type)} text-lg`} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-gray-700 truncate group-hover:text-blue-600">{anexo.nome}</p>
                                            <p className="text-[10px] text-gray-400">{formatBytes(anexo.tamanho)}</p>
                                        </div>
                                        <i className="fas fa-download text-gray-300 group-hover:text-blue-500 text-xs" />
                                    </a>
                                ))}
                            </div>
                        </Card>
                    )}

                    {/* Respostas */}
                    <Card title={`Respostas (${respostas.length})`}>
                        {respostas.length === 0 ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-comments text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma resposta ainda</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {respostas.map(resp => (
                                    <div key={resp.id} className="flex gap-3">
                                        <div className="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                                            <span className="text-xs font-bold text-blue-600">
                                                {(resp.user?.name || 'U').charAt(0).toUpperCase()}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="text-sm font-semibold text-gray-800">{resp.user?.name || 'Usuario'}</span>
                                                <span className="text-[10px] text-gray-400">
                                                    {resp.created_at ? new Date(resp.created_at).toLocaleDateString('pt-BR', {
                                                        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                                    }) : ''}
                                                </span>
                                            </div>
                                            <div className="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-3">
                                                {resp.conteudo}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Formulario de resposta */}
                        <div ref={replyRef} className="mt-6 pt-4 border-t border-gray-100">
                            <form onSubmit={submitReply} className="space-y-3">
                                <label className="block text-sm font-medium text-gray-700">Responder</label>
                                <textarea
                                    value={data.conteudo}
                                    onChange={(e) => setData('conteudo', e.target.value)}
                                    className="ds-input !h-auto"
                                    rows={4}
                                    placeholder="Digite sua resposta..."
                                    required
                                />
                                <div className="flex justify-end">
                                    <Button type="submit" loading={processing} icon="fas fa-reply"
                                        disabled={!data.conteudo.trim()}>
                                        Enviar Resposta
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </Card>
                </div>

                {/* Coluna lateral */}
                <div className="space-y-6">
                    {/* Informacoes */}
                    <Card title="Informacoes">
                        <div className="space-y-4">
                            <InfoRow label="Remetente" value={memo.remetente?.name || '-'} />
                            {memo.setor_origem && <InfoRow label="Setor de Origem" value={memo.setor_origem} />}
                            <InfoRow label="Data de Envio" value={
                                memo.created_at ? new Date(memo.created_at).toLocaleDateString('pt-BR', {
                                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                }) : '-'
                            } />
                            {memo.data_arquivamento_auto && (
                                <InfoRow label="Arquivamento Automatico" value={
                                    new Date(memo.data_arquivamento_auto).toLocaleDateString('pt-BR')
                                } />
                            )}
                        </div>
                    </Card>

                    {/* Cadeia de Tramitacao */}
                    {tramitacoes.length > 0 && (
                        <Card title={`Tramitacao (${tramitacoes.length})`}>
                            <div className="space-y-3">
                                {tramitacoes.map((t, i) => {
                                    const origem = t.origem_usuario?.name || '?';
                                    const origemUnid = t.origem_unidade?.nome;
                                    const dest = t.destino_usuario?.name || t.destino_unidade?.nome || '?';
                                    return (
                                        <div key={t.id} className={`relative pl-6 pb-3 ${i < tramitacoes.length - 1 ? 'border-l-2 border-gray-200' : ''}`}>
                                            <div className={`absolute -left-1.5 top-0 w-3 h-3 rounded-full border-2 border-white ${
                                                t.em_uso ? 'bg-blue-500' : (t.finalizado ? 'bg-green-500' : 'bg-gray-300')
                                            }`} />
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-xs text-gray-700"><strong>{origem}</strong>
                                                    {origemUnid && <span className="text-gray-400"> · {origemUnid}</span>}
                                                </span>
                                                <i className="fas fa-arrow-right text-gray-300 text-[10px]" />
                                                <span className="text-xs text-gray-700"><strong>{dest}</strong></span>
                                                {t.em_uso && ! t.finalizado && (
                                                    <span className="text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-bold uppercase">Aguardando</span>
                                                )}
                                                {t.em_uso && t.finalizado && (
                                                    <span className="text-[9px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-bold uppercase">Recebido</span>
                                                )}
                                                {! t.em_uso && (
                                                    <span className="text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-bold uppercase">Tramitado</span>
                                                )}
                                            </div>
                                            {t.parecer && <p className="text-[11px] text-gray-500 mt-1 italic">"{t.parecer}"</p>}
                                            <p className="text-[10px] text-gray-400 mt-1">
                                                {t.despachado_em ? new Date(t.despachado_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-'}
                                                {t.recebido_em && <span className="ml-2">recebido em {new Date(t.recebido_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>
                    )}

                    {/* Destinatarios */}
                    <Card title="Destinatarios">
                        {destinatarios.length === 0 ? (
                            <p className="text-xs text-gray-400 text-center py-3">Nenhum destinatario</p>
                        ) : (
                            <div className="space-y-2">
                                {destinatarios.map((dest, i) => {
                                    const user = dest.user || dest;
                                    const lido = dest.lido || dest.pivot?.lido;
                                    const lidoEm = dest.lido_em || dest.pivot?.lido_em;
                                    return (
                                        <div key={i} className="flex items-center gap-2 py-2 px-2 rounded-lg hover:bg-gray-50">
                                            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                                <span className="text-xs font-bold text-gray-500">
                                                    {(user.name || 'U').charAt(0).toUpperCase()}
                                                </span>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm text-gray-700 font-medium truncate">{user.name}</p>
                                                {lido ? (
                                                    <p className="text-[10px] text-green-600">
                                                        <i className="fas fa-check-circle mr-0.5" />
                                                        Lido {lidoEm ? 'em ' + new Date(lidoEm).toLocaleDateString('pt-BR', {
                                                            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                                        }) : ''}
                                                    </p>
                                                ) : (
                                                    <p className="text-[10px] text-gray-400">
                                                        <i className="fas fa-clock mr-0.5" />
                                                        Nao lido
                                                    </p>
                                                )}
                                            </div>
                                            {lido ? (
                                                <i className="fas fa-check-circle text-green-500 text-xs shrink-0" />
                                            ) : (
                                                <i className="fas fa-clock text-gray-300 text-xs shrink-0" />
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {/* Modal Tramitar */}
            {tramitarOpen && (
                <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={() => setTramitarOpen(false)}>
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl" onClick={(e) => e.stopPropagation()}>
                        <form onSubmit={enviarTramitar} className="p-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-bold text-gray-800"><i className="fas fa-share text-blue-500 mr-2" />Encaminhar Memorando</h3>
                                <button type="button" onClick={() => setTramitarOpen(false)} className="text-gray-400 hover:text-gray-600 text-lg">
                                    <i className="fas fa-times" />
                                </button>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-2">Encaminhar para</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {[
                                        { v: 'setor',   icone: 'fa-users', titulo: 'Setor' },
                                        { v: 'usuario', icone: 'fa-user',  titulo: 'Usuario' },
                                    ].map(op => {
                                        const ativo = tramiteForm.data.tipo_destino === op.v;
                                        return (
                                            <button key={op.v} type="button"
                                                onClick={() => tramiteForm.setData(d => ({ ...d, tipo_destino: op.v, destino_unidade_id: '', destino_usuario_id: '' }))}
                                                className={`p-3 rounded-xl border-2 transition-colors text-left ${ativo ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'}`}>
                                                <i className={`fas ${op.icone} mr-2 ${ativo ? 'text-blue-600' : 'text-gray-400'}`} />
                                                <span className="text-sm font-semibold text-gray-800">{op.titulo}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {tramiteForm.data.tipo_destino === 'setor' && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Setor de destino <span className="text-red-500">*</span></label>
                                    <select value={tramiteForm.data.destino_unidade_id}
                                        onChange={(e) => tramiteForm.setData('destino_unidade_id', e.target.value)}
                                        className="ds-input">
                                        <option value="">— Selecione o setor —</option>
                                        {unidadesTree.map(u => (
                                            <option key={u.id} value={u.id}>
                                                {'— '.repeat(Math.max(0, u.nivel - 1))}[N{u.nivel}] {u.nome}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {tramiteForm.data.tipo_destino === 'usuario' && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Filtrar por setor (opcional)</label>
                                        <select value={tramiteForm.data.destino_unidade_id}
                                            onChange={(e) => tramiteForm.setData(d => ({ ...d, destino_unidade_id: e.target.value, destino_usuario_id: '' }))}
                                            className="ds-input">
                                            <option value="">— Todos os setores —</option>
                                            {unidadesTree.map(u => (
                                                <option key={u.id} value={u.id}>
                                                    {'— '.repeat(Math.max(0, u.nivel - 1))}[N{u.nivel}] {u.nome}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Usuario de destino <span className="text-red-500">*</span></label>
                                        <select value={tramiteForm.data.destino_usuario_id}
                                            onChange={(e) => tramiteForm.setData('destino_usuario_id', e.target.value)}
                                            className="ds-input">
                                            <option value="">— Selecione o usuario —</option>
                                            {usuariosFiltrados.map(u => (
                                                <option key={u.id} value={u.id}>{u.name} · {u.email}</option>
                                            ))}
                                        </select>
                                    </div>
                                </>
                            )}

                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Parecer / Resposta (opcional)</label>
                                <textarea value={tramiteForm.data.parecer}
                                    onChange={(e) => tramiteForm.setData('parecer', e.target.value)}
                                    rows={4} className="ds-input !h-auto"
                                    placeholder="Texto que acompanha o encaminhamento..." />
                            </div>

                            <label className={`flex items-start gap-2 p-3 rounded-xl border-2 cursor-pointer transition-colors ${
                                tramiteForm.data.registrar_como_resposta ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'
                            }`}>
                                <input type="checkbox" checked={tramiteForm.data.registrar_como_resposta}
                                    onChange={(e) => tramiteForm.setData('registrar_como_resposta', e.target.checked)}
                                    className="rounded border-gray-300 text-emerald-600 mt-0.5"
                                    disabled={! tramiteForm.data.parecer.trim()} />
                                <div>
                                    <p className="text-sm font-semibold text-gray-800">
                                        <i className="fas fa-comment-dots text-emerald-500 mr-1" />
                                        Tambem registrar como resposta no thread
                                    </p>
                                    <p className="text-[11px] text-gray-500 leading-tight">
                                        O texto acima ficara visivel para o remetente original na aba Respostas e gerara notificacao para ele.
                                    </p>
                                </div>
                            </label>

                            <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <Button variant="secondary" type="button" onClick={() => setTramitarOpen(false)}>Cancelar</Button>
                                <Button type="submit" loading={tramiteForm.processing} icon="fas fa-paper-plane"
                                    disabled={
                                        (tramiteForm.data.tipo_destino === 'setor' && ! tramiteForm.data.destino_unidade_id) ||
                                        (tramiteForm.data.tipo_destino === 'usuario' && ! tramiteForm.data.destino_usuario_id)
                                    }>
                                    Encaminhar
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

/* ── Info Row ── */
function InfoRow({ label, value }) {
    return (
        <div className="bg-gray-50 rounded-lg px-4 py-3">
            <p className="text-[11px] text-gray-400 uppercase font-semibold tracking-wide">{label}</p>
            <p className="text-sm text-gray-700 mt-0.5">{value || '-'}</p>
        </div>
    );
}

/* ── Helpers ── */
function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-500';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-500';
    if (mime.includes('word')) return 'fas fa-file-word text-blue-500';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-500';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
