/**
 * Detalhe da Circular — GED
 *
 * Visualizacao completa com rastreio de leitura.
 */
import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import ClassificacaoDoItem from '../../../Components/ClassificacaoDoItem';
import MarcadoresDoDocumento from '../../../Components/MarcadoresDoDocumento';
import QuemAcessou from '../../../Components/QuemAcessou';
import { useConfirm } from '../../../Components/ConfirmProvider';
import EmitidoPorContrassenha from '../../../Components/EmitidoPorContrassenha';
import RichEditor from '../../../Components/RichEditor';
import SetoresMulti from '../../../Components/SetoresMulti';

const MODO_RESPOSTA = { abertas: ['Respostas abertas', 'fa-comments', 'bg-sky-100 text-sky-700'], restritas: ['Respostas só para o remetente', 'fa-user-lock', 'bg-amber-100 text-amber-700'], nenhuma: ['Meramente informativa', 'fa-circle-info', 'bg-gray-100 text-gray-600'] };

export default function CircularesShow({ marcadores = null, classificacao = null, acessos = [], circular, conteudo_html = '', respostas_lista = [], eu = {}, unidades = [] }) {
    const confirmar = useConfirm();
    const circ = circular || {};
    const destinatarios = circ.destinatarios || [];
    const anexos = circ.anexos || [];

    const totalLidos = destinatarios.filter(d => d.lido).length;

    const destinoDescription = () => {
        if (circ.destino_tipo === 'todos') return 'Toda a Organização';
        if (circ.destino_tipo === 'setores') {
            const setores = circ.destino_setores || [];
            return setores.length > 0 ? `Setores: ${setores.join(', ')}` : 'Setores específicos';
        }
        return `${destinatarios.length} usuário(s) específico(s)`;
    };

    return (
        <AdminLayout>
            <Head title={`Circular ${circ.numero || ''}`} />
            <EmitidoPorContrassenha documento={circ} />

            {/* Cabecalho */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <h1 className="text-2xl font-bold text-gray-800">{circ.numero}</h1>
                            <span className="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-600 font-medium">
                                <i className="fas fa-bullhorn mr-0.5" />
                                {circ.destino_tipo === 'todos' ? 'Toda Organização' : circ.destino_tipo === 'setores' ? 'Setores' : 'Usuários'}
                            </span>
                            {circ.urgente && <span className="text-[10px] px-2 py-0.5 rounded-full bg-red-600 text-white font-bold"><i className="fas fa-bolt mr-0.5" />URGENTE</span>}
                            {MODO_RESPOSTA[circ.respostas] && <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${MODO_RESPOSTA[circ.respostas][2]}`}><i className={`fas ${MODO_RESPOSTA[circ.respostas][1]} mr-0.5`} />{MODO_RESPOSTA[circ.respostas][0]}</span>}
                            {eu.arquivada && <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium"><i className="fas fa-box-archive mr-0.5" />Arquivada para você</span>}
                            {circ.status === 'arquivado' && (
                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                    <i className="fas fa-archive mr-0.5" />Arquivado
                                </span>
                            )}
                        </div>
                        <h2 className="text-lg text-gray-600">{circ.assunto}</h2>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* QR Code */}
                        {circ.qr_code_token && (
                            <div className="relative group">
                                <button className="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                                    title="QR Code de verificação">
                                    <i className="fas fa-qrcode" />
                                </button>
                                <div className="absolute right-0 top-12 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-50 hidden group-hover:block animate-fadeIn">
                                    <QRCodeSVG value={`${window.location.origin}/verificar/${circ.qr_code_token}`} size={160} />
                                    <p className="text-[10px] text-gray-400 text-center mt-2">Escaneie para verificar</p>
                                </div>
                            </div>
                        )}

                        {eu.destinatario && (
                            <>
                                <Button variant="secondary" icon={`fas ${eu.silenciada ? 'fa-bell' : 'fa-bell-slash'}`} title={eu.silenciada ? 'Voltar a ser avisado das respostas' : 'Não ser avisado das respostas'}
                                    onClick={() => router.post(`/circulares/${circ.id}/silenciar`, {}, { preserveScroll: true })}>
                                    {eu.silenciada ? 'Reativar avisos' : 'Silenciar'}
                                </Button>
                                <Button variant="secondary" icon="fas fa-box-archive" onClick={() => router.post(`/circulares/${circ.id}/arquivar-para-mim`, {}, { preserveScroll: true })}>
                                    {eu.arquivada ? 'Desarquivar' : 'Arquivar para mim'}
                                </Button>
                            </>
                        )}
                        {eu.remetente && circ.status !== 'arquivado' && (
                            <Button variant="secondary" icon="fas fa-archive"
                                onClick={async () => {
                                    if (await confirmar({ titulo: 'Arquivar esta circular para todos?', descricao: 'Ninguém mais poderá responder.', rotuloConfirmar: 'Arquivar' }))
                                        router.post(`/circulares/${circ.id}/arquivar`);
                                }}>
                                Arquivar
                            </Button>
                        )}
                        <a href={`/circulares/${circ.id}/pdf`} target="_blank" rel="noopener noreferrer"
                            className="ds-btn ds-btn-outline">
                            <i className="fas fa-file-pdf mr-1" /> PDF
                        </a>
                        <Button variant="secondary" icon="fas fa-arrow-left" href="/circulares">Voltar</Button>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Coluna principal */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Conteudo */}
                    <Card title="Conteúdo">
                        <div className="texto-rico" dangerouslySetInnerHTML={{ __html: conteudo_html }} />
                    </Card>

                    {/* Anexos */}
                    {anexos.length > 0 && (
                        <Card title={`Anexos (${anexos.length})`}>
                            <div className="space-y-2">
                                {anexos.map(anexo => (
                                    <a key={anexo.id}
                                        href={`/circulares/${circ.id}/anexos/${anexo.id}/download`}
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

                    {circ.respostas !== 'nenhuma' && <RespostasCircular circ={circ} respostas={respostas_lista} eu={eu} />}
                    {eu.remetente && circ.status !== 'arquivado' && <EncaminharCircular circ={circ} unidades={unidades} />}

                    {/* Rastreio de Leitura */}
                    <Card title={`Rastreio de Leitura (${totalLidos} de ${destinatarios.length} lido(s))`}>
                        {destinatarios.length === 0 ? (
                            <p className="text-xs text-gray-400 text-center py-3">Nenhum destinatário</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                                        <tr>
                                            <th className="px-4 py-2 text-left font-semibold">Nome</th>
                                            <th className="px-4 py-2 text-left font-semibold">E-mail</th>
                                            <th className="px-4 py-2 text-center font-semibold">Status</th>
                                            <th className="px-4 py-2 text-left font-semibold">Data/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {destinatarios.map((dest, i) => {
                                            const user = dest.usuario || dest.user || {};
                                            return (
                                                <tr key={i} className="hover:bg-gray-50">
                                                    <td className="px-4 py-2 text-gray-700">{user.name || '-'}</td>
                                                    <td className="px-4 py-2 text-gray-500 text-xs">{user.email || '-'}</td>
                                                    <td className="px-4 py-2 text-center">
                                                        {dest.lido ? (
                                                            <span className="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">
                                                                <i className="fas fa-check-circle" /> Lido
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">
                                                                <i className="fas fa-clock" /> Não lido
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2 text-gray-400 text-xs">
                                                        {dest.lido && dest.lido_em
                                                            ? new Date(dest.lido_em).toLocaleDateString('pt-BR', {
                                                                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                                            })
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>

                {/* Coluna lateral */}
                <div className="space-y-6">
                    {/* Informacoes */}
                    <Card title="Informações">
                        <div className="space-y-4">
                            <InfoRow label="Remetente" value={circ.remetente?.name || '-'} />
                            {circ.setor_origem && <InfoRow label="Setor de Origem" value={circ.setor_origem} />}
                            <InfoRow label="Destino" value={destinoDescription()} />
                            <InfoRow label="Data de Envio" value={
                                circ.created_at ? new Date(circ.created_at).toLocaleDateString('pt-BR', {
                                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                }) : '-'
                            } />
                            {circ.data_arquivamento_auto && (
                                <InfoRow label="Arquivamento Automático" value={
                                    new Date(circ.data_arquivamento_auto).toLocaleDateString('pt-BR')
                                } />
                            )}
                        </div>
                    </Card>

                    {/* Resumo de Leitura */}
                    <Card title="Resumo de Leitura">
                        <div className="text-center py-4">
                            <div className="text-3xl font-bold text-gray-800">
                                {totalLidos}<span className="text-gray-400 text-lg font-normal">/{destinatarios.length}</span>
                            </div>
                            <p className="text-xs text-gray-500 mt-1">destinatário(s) leu(leram)</p>
                            <div className="w-full bg-gray-200 rounded-full h-2 mt-3">
                                <div
                                    className="bg-green-500 h-2 rounded-full transition-all"
                                    style={{ width: `${destinatarios.length > 0 ? (totalLidos / destinatarios.length * 100) : 0}%` }}
                                />
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
            <MarcadoresDoDocumento dados={marcadores} className="mt-6" />
            <ClassificacaoDoItem classificacao={classificacao} className="mt-6" />
            <QuemAcessou acessos={acessos} />
        </AdminLayout>
    );
}

/* -- Info Row -- */
function InfoRow({ label, value }) {
    return (
        <div className="bg-gray-50 rounded-lg px-4 py-3">
            <p className="text-[11px] text-gray-400 uppercase font-semibold tracking-wide">{label}</p>
            <p className="text-sm text-gray-700 mt-0.5">{value || '-'}</p>
        </div>
    );
}

/* -- Helpers -- */
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

/** Respostas (TR 4.46.6, 4.46.11): abertas, todos veem; restritas, cada um vê a sua e o remetente vê todas. */
function RespostasCircular({ circ, respostas, eu }) {
    const form = useForm({ conteudo: '' });
    const enviar = (e) => {
        e.preventDefault();
        form.post(`/circulares/${circ.id}/responder`, { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <Card title={`Respostas (${respostas.length})`}>
            {circ.respostas === 'restritas' && (
                <p className="mb-3 text-xs text-amber-700"><i className="fas fa-user-lock mr-1" />{eu.remetente ? 'Respostas restritas: só você vê todas; cada servidor vê a própria.' : 'Sua resposta vai só para quem enviou a circular.'}</p>
            )}
            {respostas.length === 0 && <p className="text-sm text-gray-400 mb-3">Nenhuma resposta ainda.</p>}
            <ul className="space-y-3 mb-4">
                {respostas.map((r) => (
                    <li key={r.id} className={`rounded-lg border p-3 ${r.meu ? 'border-cyan-200 bg-cyan-50/40' : 'border-gray-100'}`}>
                        <p className="text-xs text-gray-500 mb-1"><strong className="text-gray-700">{r.autor}</strong> · {new Date(r.em).toLocaleString('pt-BR')}</p>
                        <div className="texto-rico" dangerouslySetInnerHTML={{ __html: r.conteudo_html }} />
                    </li>
                ))}
            </ul>
            {eu.pode_responder && (
                <form onSubmit={enviar} className="space-y-2">
                    <RichEditor html={form.data.conteudo} onChange={(v) => form.setData('conteudo', v)} minHeight={110} compacto placeholder="Escreva sua resposta…" erro={form.errors.conteudo} />
                    {form.errors.conteudo && <p className="text-xs text-red-600">{form.errors.conteudo}</p>}
                    <div className="flex justify-end"><Button type="submit" icon="fas fa-reply" loading={form.processing} disabled={! form.data.conteudo}>Responder</Button></div>
                </form>
            )}
        </Card>
    );
}

/** Encaminhar a setores que não estavam na circular (TR 4.46.10). */
function EncaminharCircular({ circ, unidades }) {
    const [setores, setSetores] = useState([]);
    const [enviando, setEnviando] = useState(false);
    const enviar = () => {
        setEnviando(true);
        router.post(`/circulares/${circ.id}/encaminhar`, { setores }, { preserveScroll: true, onFinish: () => { setEnviando(false); setSetores([]); } });
    };
    return (
        <Card title="Encaminhar a outros setores">
            <SetoresMulti unidades={unidades} valor={setores} onChange={setSetores} excluir={circ.destino_unidades || []} />
            <p className="mt-1 text-[11px] text-gray-500">Quem atende os setores escolhidos (e os subsetores) passa a receber a circular; quem já recebeu não é avisado de novo.</p>
            <div className="mt-2 flex justify-end">
                <Button icon="fas fa-share" disabled={! setores.length} loading={enviando} onClick={enviar}>Encaminhar</Button>
            </div>
        </Card>
    );
}