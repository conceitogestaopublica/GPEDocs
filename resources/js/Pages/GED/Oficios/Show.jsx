/**
 * Detalhe do Oficio Eletronico — GED
 *
 * Visualizacao completa com timeline de rastreio e respostas.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

const STATUS_MAP = {
    rascunho:   { label: 'Rascunho',   color: 'bg-gray-100 text-gray-600',   icon: 'fas fa-edit' },
    enviado:    { label: 'Enviado',     color: 'bg-blue-100 text-blue-700',   icon: 'fas fa-paper-plane' },
    entregue:   { label: 'Entregue',    color: 'bg-yellow-100 text-yellow-700', icon: 'fas fa-envelope' },
    lido:       { label: 'Lido',        color: 'bg-green-100 text-green-700', icon: 'fas fa-eye' },
    respondido: { label: 'Respondido',  color: 'bg-purple-100 text-purple-700', icon: 'fas fa-reply' },
    arquivado:  { label: 'Arquivado',   color: 'bg-gray-100 text-gray-500',  icon: 'fas fa-archive' },
};

const TIMELINE_STEPS = ['enviado', 'entregue', 'lido', 'respondido'];

export default function OficiosShow({ oficio }) {
    const of = oficio || {};
    const respostas = of.respostas || [];
    const anexos = of.anexos || [];
    const replyRef = useRef(null);

    const { data, setData, post, processing, reset } = useForm({
        conteudo: '',
    });

    const submitReply = (e) => {
        e.preventDefault();
        post(`/oficios/${of.id}/responder`, {
            onSuccess: () => reset(),
            preserveScroll: true,
        });
    };

    const scrollToReply = () => {
        replyRef.current?.scrollIntoView({ behavior: 'smooth' });
        replyRef.current?.querySelector('textarea')?.focus();
    };

    const st = STATUS_MAP[of.status] || STATUS_MAP.enviado;

    // Determine which timeline steps are completed
    const getStepStatus = (step) => {
        const order = { enviado: 1, entregue: 2, lido: 3, respondido: 4 };
        const currentOrder = {
            enviado: 1,
            entregue: 2,
            lido: 3,
            respondido: 4,
            arquivado: 4,
        };
        const current = currentOrder[of.status] || 0;
        return order[step] <= current;
    };

    const getStepDate = (step) => {
        const dateMap = {
            enviado: of.enviado_em,
            entregue: of.entregue_em,
            lido: of.lido_em,
            respondido: respostas.length > 0 ? respostas[respostas.length - 1].created_at : null,
        };
        const d = dateMap[step];
        if (!d) return null;
        return new Date(d).toLocaleDateString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    };

    return (
        <AdminLayout>
            <Head title={`Oficio ${of.numero || ''}`} />

            {/* Cabecalho */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <h1 className="text-2xl font-bold text-gray-800">{of.numero}</h1>
                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}>
                                <i className={`${st.icon} mr-0.5`} />{st.label}
                            </span>
                        </div>
                        <h2 className="text-lg text-gray-600">{of.assunto}</h2>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* QR Code */}
                        {of.qr_code_token && (
                            <div className="relative group">
                                <button className="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                                    title="QR Code de verificacao">
                                    <i className="fas fa-qrcode" />
                                </button>
                                <div className="absolute right-0 top-12 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-50 hidden group-hover:block animate-fadeIn">
                                    <QRCodeSVG value={`${window.location.origin}/oficios/verificar/${of.qr_code_token}`} size={160} />
                                    <p className="text-[10px] text-gray-400 text-center mt-2">Escaneie para verificar</p>
                                </div>
                            </div>
                        )}

                        {of.status !== 'arquivado' && (
                            <Button variant="secondary" icon="fas fa-archive"
                                onClick={() => {
                                    if (confirm('Arquivar este oficio?'))
                                        router.post(`/oficios/${of.id}/arquivar`);
                                }}>
                                Arquivar
                            </Button>
                        )}
                        <a href={`/oficios/${of.id}/pdf`} target="_blank" rel="noopener noreferrer"
                            className="ds-btn ds-btn-outline">
                            <i className="fas fa-file-pdf mr-1" /> PDF
                        </a>
                        <Button icon="fas fa-reply" onClick={scrollToReply}>Responder</Button>
                        <Button variant="secondary" icon="fas fa-arrow-left" href="/oficios">Voltar</Button>
                    </div>
                </div>

                {/* Timeline de rastreio */}
                <div className="mt-6 pt-4 border-t border-gray-100">
                    <div className="flex items-center justify-between max-w-2xl mx-auto">
                        {TIMELINE_STEPS.map((step, i) => {
                            const completed = getStepStatus(step);
                            const stepDate = getStepDate(step);
                            const stepInfo = STATUS_MAP[step];

                            return (
                                <div key={step} className="flex items-center flex-1">
                                    <div className="flex flex-col items-center">
                                        <div className={`w-10 h-10 rounded-full flex items-center justify-center border-2 transition-colors
                                            ${completed
                                                ? 'bg-green-100 border-green-500 text-green-600'
                                                : 'bg-gray-50 border-gray-200 text-gray-300'
                                            }`}>
                                            <i className={`${stepInfo.icon} text-sm`} />
                                        </div>
                                        <p className={`text-[10px] mt-1.5 font-medium ${completed ? 'text-green-700' : 'text-gray-400'}`}>
                                            {stepInfo.label}
                                        </p>
                                        {stepDate && (
                                            <p className="text-[9px] text-gray-400">{stepDate}</p>
                                        )}
                                    </div>
                                    {i < TIMELINE_STEPS.length - 1 && (
                                        <div className={`flex-1 h-0.5 mx-2 mt-[-20px] ${completed && getStepStatus(TIMELINE_STEPS[i + 1]) ? 'bg-green-400' : completed ? 'bg-green-200' : 'bg-gray-200'}`} />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Coluna principal */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Conteudo */}
                    <Card title="Conteudo">
                        <div className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                            {of.conteudo}
                        </div>
                    </Card>

                    {/* Anexos */}
                    {anexos.length > 0 && (
                        <Card title={`Anexos (${anexos.length})`}>
                            <div className="space-y-2">
                                {anexos.map(anexo => (
                                    <a key={anexo.id}
                                        href={`/oficios/${of.id}/anexos/${anexo.id}/download`}
                                        className="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition-colors group">
                                        <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                                            <i className={`${getFileIcon(anexo.mime_type)} text-lg`} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-gray-700 truncate group-hover:text-blue-600">{anexo.nome}</p>
                                            <div className="flex items-center gap-2">
                                                <p className="text-[10px] text-gray-400">{formatBytes(anexo.tamanho)}</p>
                                                {anexo.solicitar_assinatura && (
                                                    <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-600 font-medium">
                                                        <i className="fas fa-signature mr-0.5" />Assinatura solicitada
                                                    </span>
                                                )}
                                            </div>
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
                                {respostas.map(resp => {
                                    const isExterno = resp.externo;
                                    const nome = isExterno
                                        ? (resp.respondente_nome || 'Destinatario Externo')
                                        : (resp.usuario?.name || resp.respondente_nome || 'Usuario');

                                    return (
                                        <div key={resp.id} className="flex gap-3">
                                            <div className={`w-9 h-9 rounded-full flex items-center justify-center shrink-0 ${isExterno ? 'bg-purple-100' : 'bg-blue-100'}`}>
                                                <span className={`text-xs font-bold ${isExterno ? 'text-purple-600' : 'text-blue-600'}`}>
                                                    {nome.charAt(0).toUpperCase()}
                                                </span>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-sm font-semibold text-gray-800">{nome}</span>
                                                    {isExterno && (
                                                        <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600 font-medium">
                                                            Externo
                                                        </span>
                                                    )}
                                                    <span className="text-[10px] text-gray-400">
                                                        {resp.created_at ? new Date(resp.created_at).toLocaleDateString('pt-BR', {
                                                            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                                        }) : ''}
                                                    </span>
                                                </div>
                                                <div className={`text-sm text-gray-700 whitespace-pre-wrap rounded-lg p-3 ${isExterno ? 'bg-purple-50' : 'bg-gray-50'}`}>
                                                    {resp.conteudo}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
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
                    {/* Destinatario */}
                    <Card title="Destinatario">
                        <div className="space-y-4">
                            <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
                                <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                                    <i className="fas fa-user text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-gray-800">{of.destinatario_nome}</p>
                                    <p className="text-xs text-gray-500">{of.destinatario_email}</p>
                                </div>
                            </div>
                            {of.destinatario_cargo && (
                                <InfoRow label="Cargo" value={of.destinatario_cargo} />
                            )}
                            {of.destinatario_orgao && (
                                <InfoRow label="Orgao/Instituicao" value={of.destinatario_orgao} />
                            )}
                        </div>
                    </Card>

                    {/* Informacoes */}
                    <Card title="Informacoes">
                        <div className="space-y-4">
                            <InfoRow label="Remetente" value={of.remetente?.name || '-'} />
                            {of.setor_origem && <InfoRow label="Setor de Origem" value={of.setor_origem} />}
                            <InfoRow label="Enviado em" value={
                                of.enviado_em ? new Date(of.enviado_em).toLocaleDateString('pt-BR', {
                                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                }) : '-'
                            } />
                            {of.entregue_em && (
                                <InfoRow label="Entregue em" value={
                                    new Date(of.entregue_em).toLocaleDateString('pt-BR', {
                                        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                    })
                                } />
                            )}
                            {of.lido_em && (
                                <InfoRow label="Lido em" value={
                                    new Date(of.lido_em).toLocaleDateString('pt-BR', {
                                        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                    })
                                } />
                            )}
                            {of.arquivado_em && (
                                <InfoRow label="Arquivado em" value={
                                    new Date(of.arquivado_em).toLocaleDateString('pt-BR', {
                                        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                    })
                                } />
                            )}
                        </div>
                    </Card>

                    {/* Rastreio */}
                    <Card title="Rastreio de Entrega">
                        <div className="text-xs text-gray-500 space-y-2">
                            <p>
                                <i className="fas fa-link text-gray-400 mr-1" />
                                O link de rastreio foi enviado junto ao oficio. Quando o destinatario abrir,
                                a data/hora sera registrada automaticamente.
                            </p>
                            {of.rastreio_token && (
                                <div className="bg-gray-50 rounded-lg p-3 mt-2">
                                    <p className="text-[10px] text-gray-400 uppercase font-semibold tracking-wide mb-1">Token de Rastreio</p>
                                    <p className="text-[10px] text-gray-600 font-mono break-all">{of.rastreio_token}</p>
                                </div>
                            )}
                        </div>
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
