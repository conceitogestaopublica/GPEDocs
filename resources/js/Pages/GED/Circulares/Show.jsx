/**
 * Detalhe da Circular — GED
 *
 * Visualizacao completa com rastreio de leitura.
 */
import { Head, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function CircularesShow({ circular }) {
    const circ = circular || {};
    const destinatarios = circ.destinatarios || [];
    const anexos = circ.anexos || [];

    const totalLidos = destinatarios.filter(d => d.lido).length;

    const destinoDescription = () => {
        if (circ.destino_tipo === 'todos') return 'Toda a Organizacao';
        if (circ.destino_tipo === 'setores') {
            const setores = circ.destino_setores || [];
            return setores.length > 0 ? `Setores: ${setores.join(', ')}` : 'Setores especificos';
        }
        return `${destinatarios.length} usuario(s) especifico(s)`;
    };

    return (
        <AdminLayout>
            <Head title={`Circular ${circ.numero || ''}`} />

            {/* Cabecalho */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <h1 className="text-2xl font-bold text-gray-800">{circ.numero}</h1>
                            <span className="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-600 font-medium">
                                <i className="fas fa-bullhorn mr-0.5" />
                                {circ.destino_tipo === 'todos' ? 'Toda Organizacao' : circ.destino_tipo === 'setores' ? 'Setores' : 'Usuarios'}
                            </span>
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
                                    title="QR Code de verificacao">
                                    <i className="fas fa-qrcode" />
                                </button>
                                <div className="absolute right-0 top-12 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-50 hidden group-hover:block animate-fadeIn">
                                    <QRCodeSVG value={`${window.location.origin}/verificar/${circ.qr_code_token}`} size={160} />
                                    <p className="text-[10px] text-gray-400 text-center mt-2">Escaneie para verificar</p>
                                </div>
                            </div>
                        )}

                        {circ.status !== 'arquivado' && (
                            <Button variant="secondary" icon="fas fa-archive"
                                onClick={() => {
                                    if (confirm('Arquivar esta circular?'))
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
                    <Card title="Conteudo">
                        <div className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                            {circ.conteudo}
                        </div>
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

                    {/* Rastreio de Leitura */}
                    <Card title={`Rastreio de Leitura (${totalLidos} de ${destinatarios.length} lido(s))`}>
                        {destinatarios.length === 0 ? (
                            <p className="text-xs text-gray-400 text-center py-3">Nenhum destinatario</p>
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
                                                                <i className="fas fa-clock" /> Nao lido
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
                    <Card title="Informacoes">
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
                                <InfoRow label="Arquivamento Automatico" value={
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
                            <p className="text-xs text-gray-500 mt-1">destinatario(s) leu(leram)</p>
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
