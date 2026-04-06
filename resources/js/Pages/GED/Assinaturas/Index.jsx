/**
 * Assinaturas Pendentes — GED
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export default function Assinaturas({ pendentes, assinadas }) {
    const [activeTab, setActiveTab] = useState('pendentes');
    const [assinarModal, setAssinarModal] = useState(null);
    const [recusarModal, setRecusarModal] = useState(null);

    return (
        <AdminLayout>
            <Head title="Assinaturas" />
            <PageHeader title="Assinaturas" subtitle="Documentos aguardando sua assinatura" />

            {/* Tabs */}
            <div className="flex gap-2 mb-6">
                {[
                    { key: 'pendentes', label: `Pendentes (${(pendentes || []).length})`, icon: 'fas fa-clock' },
                    { key: 'assinadas', label: 'Assinadas', icon: 'fas fa-check' },
                ].map(tab => (
                    <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors
                            ${activeTab === tab.key ? 'bg-blue-600 text-white shadow-md' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}>
                        <i className={`${tab.icon} text-xs`} />
                        {tab.label}
                    </button>
                ))}
            </div>

            {activeTab === 'pendentes' && (
                <Card padding={false}>
                    {(pendentes || []).length === 0 ? (
                        <div className="py-12 text-center text-gray-400">
                            <i className="fas fa-check-circle text-3xl mb-2 block" />
                            <p>Nenhuma assinatura pendente</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {(pendentes || []).map(a => (
                                <div key={a.id} className="flex items-center justify-between px-5 py-4 hover:bg-gray-50">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                            <i className="fas fa-file-signature text-amber-600 text-sm" />
                                        </div>
                                        <div>
                                            <Link href={`/documentos/${a.documento?.id}`} className="text-sm font-medium text-gray-800 hover:text-blue-600">
                                                {a.documento?.nome}
                                            </Link>
                                            <p className="text-xs text-gray-400">
                                                Solicitado por {a.solicitacao?.solicitante?.name} em {new Date(a.created_at).toLocaleDateString('pt-BR')}
                                            </p>
                                            {a.solicitacao?.prazo && (
                                                <p className="text-[10px] text-orange-500">
                                                    <i className="fas fa-clock mr-1" />
                                                    Prazo: {new Date(a.solicitacao.prazo).toLocaleDateString('pt-BR')}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button size="sm" icon="fas fa-pen-nib" onClick={() => setAssinarModal(a)}>
                                            Assinar
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => setRecusarModal(a)}>
                                            Recusar
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            )}

            {activeTab === 'assinadas' && (
                <Card padding={false}>
                    {(assinadas || []).length === 0 ? (
                        <div className="py-12 text-center text-gray-400">
                            <i className="fas fa-file-signature text-3xl mb-2 block" />
                            <p>Nenhuma assinatura realizada</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {(assinadas || []).map(a => (
                                <div key={a.id} className="flex items-center justify-between px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <i className="fas fa-check text-green-600 text-sm" />
                                        </div>
                                        <div>
                                            <Link href={`/documentos/${a.documento?.id}`} className="text-sm font-medium text-gray-800 hover:text-blue-600">
                                                {a.documento?.nome}
                                            </Link>
                                            <p className="text-xs text-gray-400">
                                                Assinado em {a.assinado_em ? new Date(a.assinado_em).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Assinado</span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            )}

            <AssinarModal assinatura={assinarModal} onClose={() => setAssinarModal(null)} />
            <RecusarModal assinatura={recusarModal} onClose={() => setRecusarModal(null)} />
        </AdminLayout>
    );
}

function AssinarModal({ assinatura, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        cpf: '',
        geolocalizacao: '',
    });
    const [concordo, setConcordo] = useState(false);

    const requestGeo = () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => setData('geolocalizacao', `${pos.coords.latitude},${pos.coords.longitude}`),
                () => {} // Geo denied - proceed without
            );
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (!concordo) return;
        requestGeo();
        post(`/assinaturas/${assinatura.id}/assinar`, { onSuccess: onClose });
    };

    if (!assinatura) return null;

    return (
        <Modal show={!!assinatura} onClose={onClose} title="Assinar Documento">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-blue-50 rounded-xl p-4">
                    <p className="text-sm font-medium text-blue-800">{assinatura.documento?.nome}</p>
                    {assinatura.solicitacao?.mensagem && (
                        <p className="text-xs text-blue-600 mt-1">{assinatura.solicitacao.mensagem}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <input type="text" value={data.cpf} onChange={(e) => setData('cpf', e.target.value)}
                        className="ds-input" placeholder="000.000.000-00" maxLength={14} />
                    {errors.cpf && <p className="mt-1 text-xs text-red-600">{errors.cpf}</p>}
                </div>

                <div className="bg-gray-50 rounded-xl p-3">
                    <p className="text-[10px] text-gray-500">
                        <i className="fas fa-info-circle mr-1" />
                        Ao assinar, serao coletados: e-mail, CPF, endereco IP, geolocalizacao (se permitida),
                        data/hora e hash do documento conforme Lei 14.063/2020.
                    </p>
                </div>

                <label className="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" checked={concordo} onChange={(e) => setConcordo(e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 mt-0.5" />
                    <span className="text-sm text-gray-700">
                        Declaro que li e concordo com o conteudo deste documento
                    </span>
                </label>

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-pen-nib" disabled={!concordo}>
                        Assinar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function RecusarModal({ assinatura, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '' });

    const submit = (e) => {
        e.preventDefault();
        post(`/assinaturas/${assinatura.id}/recusar`, { onSuccess: onClose });
    };

    if (!assinatura) return null;

    return (
        <Modal show={!!assinatura} onClose={onClose} title="Recusar Assinatura">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-red-50 rounded-xl p-4">
                    <p className="text-sm font-medium text-red-800">{assinatura.documento?.nome}</p>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Motivo da recusa</label>
                    <textarea value={data.motivo} onChange={(e) => setData('motivo', e.target.value)}
                        className="ds-input !h-auto" rows={3} placeholder="Informe o motivo..." />
                    {errors.motivo && <p className="mt-1 text-xs text-red-600">{errors.motivo}</p>}
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="danger" type="submit" loading={processing} icon="fas fa-times">Recusar</Button>
                </div>
            </form>
        </Modal>
    );
}
