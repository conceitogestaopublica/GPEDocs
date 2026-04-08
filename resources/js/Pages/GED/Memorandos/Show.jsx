/**
 * Detalhe do Memorando — GED
 *
 * Visualizacao completa com respostas em thread.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function MemorandosShow({ memorando }) {
    const memo = memorando || {};
    const respostas = memo.respostas || [];
    const destinatarios = memo.destinatarios || [];
    const anexos = memo.anexos || [];
    const replyRef = useRef(null);

    const { data, setData, post, processing, reset } = useForm({
        conteudo: '',
    });

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

    return (
        <AdminLayout>
            <Head title={`Memorando ${memo.numero || ''}`} />

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
