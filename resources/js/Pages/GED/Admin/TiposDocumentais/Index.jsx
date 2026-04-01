/**
 * Administracao de Tipos Documentais — GED
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import Modal from '../../../../Components/Modal';
import Card from '../../../../Components/Card';

const TIPOS_CAMPO = [
    { value: 'text', label: 'Texto' },
    { value: 'number', label: 'Numero' },
    { value: 'date', label: 'Data' },
    { value: 'select', label: 'Lista de opcoes' },
];

export default function TiposDocumentais({ tipos }) {
    const [showForm, setShowForm] = useState(false);
    const [editTipo, setEditTipo] = useState(null);
    const [deleteTipo, setDeleteTipo] = useState(null);

    const openCreate = () => { setEditTipo(null); setShowForm(true); };
    const openEdit = (tipo) => { setEditTipo(tipo); setShowForm(true); };

    return (
        <AdminLayout>
            <Head title="Tipos Documentais" />

            <PageHeader title="Tipos Documentais" subtitle="Defina os tipos de documentos e seus metadados">
                <Button icon="fas fa-plus" onClick={openCreate}>
                    Novo Tipo
                </Button>
            </PageHeader>

            <Card padding={false}>
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th className="px-4 py-3 text-left font-semibold">Nome</th>
                            <th className="px-4 py-3 text-left font-semibold">Descricao</th>
                            <th className="px-4 py-3 text-center font-semibold">Campos</th>
                            <th className="px-4 py-3 text-center font-semibold">Documentos</th>
                            <th className="px-4 py-3 text-center font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold w-32">Acoes</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {(tipos || []).map(tipo => (
                            <tr key={tipo.id} className={`hover:bg-gray-50 ${!tipo.ativo ? 'opacity-50' : ''}`}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center shrink-0">
                                            <i className="fas fa-file-signature text-xs text-violet-600" />
                                        </div>
                                        <span className="font-medium text-gray-800">{tipo.nome}</span>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-gray-500 max-w-xs truncate">{tipo.descricao || '-'}</td>
                                <td className="px-4 py-3 text-center">
                                    <span className="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-medium">
                                        {(tipo.schema_metadados || []).length}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-center text-gray-500">{tipo.documentos_count || 0}</td>
                                <td className="px-4 py-3 text-center">
                                    <button
                                        onClick={() => router.post(`/admin/tipos-documentais/${tipo.id}/toggle-ativo`)}
                                        className={`text-[10px] px-2.5 py-1 rounded-full font-medium cursor-pointer transition-colors
                                            ${tipo.ativo
                                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                                    >
                                        {tipo.ativo ? 'Ativo' : 'Inativo'}
                                    </button>
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        <button onClick={() => openEdit(tipo)}
                                            className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                                            title="Editar">
                                            <i className="fas fa-pen text-xs" />
                                        </button>
                                        {(tipo.documentos_count || 0) === 0 && (
                                            <button onClick={() => setDeleteTipo(tipo)}
                                                className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                title="Excluir">
                                                <i className="fas fa-trash text-xs" />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {(!tipos || tipos.length === 0) && (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-file-signature text-3xl mb-2 block" />
                        <p>Nenhum tipo documental cadastrado</p>
                    </div>
                )}
            </Card>

            <TipoFormModal show={showForm} onClose={() => setShowForm(false)} tipo={editTipo} />
            <DeleteTipoModal tipo={deleteTipo} onClose={() => setDeleteTipo(null)} />
        </AdminLayout>
    );
}

/* ── Modal: Criar/Editar Tipo Documental ── */
function TipoFormModal({ show, onClose, tipo }) {
    const isEdit = !!tipo;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        schema_metadados: [],
    });

    // Sync form when opening
    useState(() => {
        if (tipo) {
            setData({
                nome: tipo.nome,
                descricao: tipo.descricao || '',
                schema_metadados: tipo.schema_metadados || [],
            });
        } else {
            reset();
        }
    }, [tipo, show]);

    // Reset on open
    const handleOpen = () => {
        if (tipo) {
            setData({
                nome: tipo.nome,
                descricao: tipo.descricao || '',
                schema_metadados: tipo.schema_metadados || [],
            });
        } else {
            setData({ nome: '', descricao: '', schema_metadados: [] });
        }
    };

    // Sync when tipo changes
    if (show && tipo && data.nome !== tipo.nome && data.nome === '') {
        handleOpen();
    }
    if (show && !tipo && data.nome !== '') {
        // reset for create
    }

    const addCampo = () => {
        setData('schema_metadados', [
            ...data.schema_metadados,
            { campo: '', tipo: 'text', label: '', obrigatorio: false, opcoes: '' },
        ]);
    };

    const updateCampo = (index, field, value) => {
        const updated = [...data.schema_metadados];
        updated[index] = { ...updated[index], [field]: value };
        // Auto-gerar campo a partir do label
        if (field === 'label' && !updated[index]._campoManual) {
            updated[index].campo = value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
        }
        setData('schema_metadados', updated);
    };

    const removeCampo = (index) => {
        setData('schema_metadados', data.schema_metadados.filter((_, i) => i !== index));
    };

    const moveCampo = (index, offset) => {
        const newIdx = index + offset;
        if (newIdx < 0 || newIdx >= data.schema_metadados.length) return;
        const copy = [...data.schema_metadados];
        [copy[index], copy[newIdx]] = [copy[newIdx], copy[index]];
        setData('schema_metadados', copy);
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/tipos-documentais/${tipo.id}`, { onSuccess: onClose });
        } else {
            post('/admin/tipos-documentais', { onSuccess: () => { reset(); onClose(); } });
        }
    };

    return (
        <Modal show={show} onClose={onClose} title={isEdit ? 'Editar Tipo Documental' : 'Novo Tipo Documental'} maxWidth="2xl">
            <form onSubmit={submit} className="space-y-5">
                {/* Info basica */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                            className="ds-input" placeholder="Ex: Oficio, Contrato..." autoFocus />
                        {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                        <input type="text" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                            className="ds-input" placeholder="Descricao breve" />
                    </div>
                </div>

                {/* Campos de metadados */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <label className="text-sm font-medium text-gray-700">Campos de Metadados</label>
                        <button type="button" onClick={addCampo}
                            className="flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 px-2 py-1 rounded-lg hover:bg-blue-50 transition-colors">
                            <i className="fas fa-plus text-[10px]" />
                            Adicionar campo
                        </button>
                    </div>

                    {data.schema_metadados.length === 0 ? (
                        <div className="text-center py-6 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                            <i className="fas fa-list-alt text-2xl text-gray-300 mb-2 block" />
                            <p className="text-sm text-gray-400">Nenhum campo definido</p>
                            <button type="button" onClick={addCampo}
                                className="mt-2 text-xs text-blue-600 hover:underline">
                                Adicionar primeiro campo
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-3 max-h-64 overflow-y-auto pr-1">
                            {data.schema_metadados.map((campo, idx) => (
                                <div key={idx} className="flex items-start gap-2 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                    <div className="flex flex-col gap-1 mt-1">
                                        <button type="button" onClick={() => moveCampo(idx, -1)}
                                            className="text-gray-400 hover:text-gray-600 text-[10px]" disabled={idx === 0}>
                                            <i className="fas fa-chevron-up" />
                                        </button>
                                        <button type="button" onClick={() => moveCampo(idx, 1)}
                                            className="text-gray-400 hover:text-gray-600 text-[10px]" disabled={idx === data.schema_metadados.length - 1}>
                                            <i className="fas fa-chevron-down" />
                                        </button>
                                    </div>

                                    <div className="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        <div className="sm:col-span-2">
                                            <input type="text" value={campo.label}
                                                onChange={(e) => updateCampo(idx, 'label', e.target.value)}
                                                className="ds-input !text-xs" placeholder="Label (ex: Numero do Oficio)" />
                                        </div>
                                        <div>
                                            <select value={campo.tipo}
                                                onChange={(e) => updateCampo(idx, 'tipo', e.target.value)}
                                                className="ds-input !text-xs">
                                                {TIPOS_CAMPO.map(t => (
                                                    <option key={t.value} value={t.value}>{t.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                                <input type="checkbox" checked={!!campo.obrigatorio}
                                                    onChange={(e) => updateCampo(idx, 'obrigatorio', e.target.checked)}
                                                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                                Obrig.
                                            </label>
                                        </div>
                                        {campo.tipo === 'select' && (
                                            <div className="sm:col-span-4">
                                                <input type="text" value={campo.opcoes || ''}
                                                    onChange={(e) => updateCampo(idx, 'opcoes', e.target.value)}
                                                    className="ds-input !text-xs" placeholder="Opcoes separadas por virgula (ex: Sim,Nao,Talvez)" />
                                            </div>
                                        )}
                                    </div>

                                    <button type="button" onClick={() => removeCampo(idx)}
                                        className="mt-1 text-red-400 hover:text-red-600 transition-colors p-1">
                                        <i className="fas fa-times text-xs" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                    {errors.schema_metadados && <p className="mt-1 text-xs text-red-600">{errors.schema_metadados}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon={isEdit ? 'fas fa-save' : 'fas fa-plus'}>
                        {isEdit ? 'Salvar' : 'Criar Tipo'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Modal: Excluir Tipo ── */
function DeleteTipoModal({ tipo, onClose }) {
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.delete(`/admin/tipos-documentais/${tipo.id}`, {
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    if (!tipo) return null;

    return (
        <Modal show={!!tipo} onClose={onClose} title="Excluir Tipo Documental">
            <div className="space-y-4">
                <div className="flex items-start gap-3 p-4 bg-red-50 rounded-xl">
                    <i className="fas fa-exclamation-triangle text-red-500 mt-0.5" />
                    <div>
                        <p className="text-sm font-medium text-red-800">Tem certeza?</p>
                        <p className="text-sm text-red-600 mt-1">
                            O tipo <strong>"{tipo.nome}"</strong> sera excluido permanentemente.
                        </p>
                    </div>
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="danger" onClick={submit} loading={processing} icon="fas fa-trash">Excluir</Button>
                </div>
            </div>
        </Modal>
    );
}
