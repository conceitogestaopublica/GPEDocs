/**
 * Modelos de Oficio — administracao
 * Permite cadastrar/editar templates que aparecem no Create de oficio.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import Card from '../../../../Components/Card';
import Modal from '../../../../Components/Modal';
import RichEditor from '../../../../Components/RichEditor';

export default function ModelosOficio({ modelos = [] }) {
    const [editando, setEditando] = useState(null); // null | 'novo' | objeto modelo
    const [confirmExcluir, setConfirmExcluir] = useState(null);

    return (
        <AdminLayout>
            <Head title="Modelos de Oficio" />
            <PageHeader title="Modelos de Oficio" subtitle="Templates reutilizaveis ao criar novos oficios">
                <Button icon="fas fa-plus" onClick={() => setEditando('novo')}>Novo Modelo</Button>
            </PageHeader>

            <Card padding={false}>
                {modelos.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-file-alt text-3xl mb-2 block" />
                        <p>Nenhum modelo cadastrado</p>
                        <p className="text-xs mt-1">Crie modelos para agilizar a redacao de oficios recorrentes</p>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left">Nome</th>
                                <th className="px-4 py-3 text-left">Categoria</th>
                                <th className="px-4 py-3 text-left">Descricao</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-center w-32">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {modelos.map(m => (
                                <tr key={m.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-800">{m.nome}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{m.categoria || '-'}</td>
                                    <td className="px-4 py-3 text-xs text-gray-500 max-w-md truncate">{m.descricao || '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium
                                            ${m.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {m.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <div className="flex justify-center gap-2">
                                            <button onClick={() => setEditando(m)}
                                                className="text-blue-600 hover:text-blue-800 text-xs">
                                                <i className="fas fa-edit" /> Editar
                                            </button>
                                            <button onClick={() => setConfirmExcluir(m)}
                                                className="text-red-500 hover:text-red-700 text-xs">
                                                <i className="fas fa-trash" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            {editando && (
                <ModeloModal modelo={editando === 'novo' ? null : editando}
                    onClose={() => setEditando(null)} />
            )}

            {confirmExcluir && (
                <ConfirmExcluirModal modelo={confirmExcluir} onClose={() => setConfirmExcluir(null)} />
            )}
        </AdminLayout>
    );
}

function ModeloModal({ modelo, onClose }) {
    const isEdit = !!modelo;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: modelo?.nome || '',
        categoria: modelo?.categoria || '',
        descricao: modelo?.descricao || '',
        conteudo: modelo?.conteudo || '',
        ativo: modelo?.ativo ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: () => { reset(); onClose(); } };
        if (isEdit) put(`/admin/oficios-modelos/${modelo.id}`, opts);
        else post('/admin/oficios-modelos', opts);
    };

    const [importando, setImportando] = useState(false);
    const [erroImport, setErroImport] = useState(null);
    const [modoEdicao, setModoEdicao] = useState('visual'); // visual | html

    const importarArquivo = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setErroImport(null);
        setImportando(true);
        try {
            const fd = new FormData();
            fd.append('arquivo', file);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const resp = await fetch('/admin/oficios-modelos/importar-docx', {
                method: 'POST',
                body: fd,
                headers: csrf ? { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } : { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error(json.erro || json.message || 'Falha na importacao');
            setData('conteudo', json.conteudo || '');
            if (!data.nome) {
                // Sugere nome a partir do arquivo
                const base = (json.nome_arquivo || file.name).replace(/\.[^.]+$/, '');
                setData('nome', base);
            }
        } catch (err) {
            setErroImport(err.message);
        } finally {
            setImportando(false);
            e.target.value = '';
        }
    };

    return (
        <Modal show onClose={onClose} title={isEdit ? `Editar: ${modelo.nome}` : 'Novo Modelo de Oficio'} maxWidth="4xl">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Nome *</label>
                        <input type="text" value={data.nome} onChange={e => setData('nome', e.target.value)}
                            className="ds-input" placeholder="Ex: Encaminhamento Padrao" />
                        {errors.nome && <p className="text-xs text-red-600 mt-1">{errors.nome}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Categoria</label>
                        <input type="text" value={data.categoria} onChange={e => setData('categoria', e.target.value)}
                            className="ds-input" placeholder="Ex: Comunicacao, Solicitacao" />
                    </div>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Descricao (uso interno)</label>
                    <input type="text" value={data.descricao} onChange={e => setData('descricao', e.target.value)}
                        className="ds-input" placeholder="Para que serve esse modelo?" />
                </div>

                <div>
                    <div className="flex items-center justify-between mb-1 gap-2 flex-wrap">
                        <label className="text-xs font-medium text-gray-700">Conteudo *</label>
                        <div className="flex items-center gap-2">
                            <div className="flex rounded-md border border-gray-200 overflow-hidden text-[10px]">
                                <button type="button" onClick={() => setModoEdicao('visual')}
                                    className={`px-2 py-1 ${modoEdicao === 'visual' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'}`}>
                                    <i className="fas fa-eye mr-1" />Visual
                                </button>
                                <button type="button" onClick={() => setModoEdicao('html')}
                                    className={`px-2 py-1 ${modoEdicao === 'html' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'}`}>
                                    <i className="fas fa-code mr-1" />HTML
                                </button>
                            </div>
                            <label className={`text-xs px-2 py-1 rounded-md cursor-pointer transition-colors
                                ${importando ? 'bg-gray-100 text-gray-400 cursor-wait' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'}`}>
                                {importando ? <><i className="fas fa-spinner fa-spin mr-1" />Importando...</> :
                                    <><i className="fas fa-file-import mr-1" />Importar arquivo Word/TXT</>}
                                <input type="file" accept=".doc,.docx,.txt,.html,.htm" onChange={importarArquivo}
                                    disabled={importando} className="hidden" />
                            </label>
                        </div>
                    </div>

                    {erroImport && (
                        <div className="mb-2 px-3 py-2 bg-red-50 border border-red-200 rounded-md text-xs text-red-700">
                            <i className="fas fa-exclamation-triangle mr-1" />{erroImport}
                        </div>
                    )}

                    {modoEdicao === 'visual' ? (
                        <RichEditor
                            html={data.conteudo}
                            onChange={(html) => setData('conteudo', html)}
                        />
                    ) : (
                        <textarea value={data.conteudo} onChange={e => setData('conteudo', e.target.value)}
                            rows={18} className="ds-input font-mono text-xs"
                            placeholder="HTML do modelo. Use {{destinatario}}, {{cargo}}, {{orgao}}, {{assunto}} para campos dinamicos." />
                    )}

                    {errors.conteudo && <p className="text-xs text-red-600 mt-1">{errors.conteudo}</p>}
                    <p className="text-[10px] text-gray-500 mt-1">
                        Variaveis disponiveis: <code>{'{{destinatario}}'}</code> <code>{'{{cargo}}'}</code>{' '}
                        <code>{'{{orgao}}'}</code> <code>{'{{assunto}}'}</code> <code>{'{{data}}'}</code>
                    </p>
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={data.ativo} onChange={e => setData('ativo', e.target.checked)}
                        className="rounded border-gray-300 text-blue-600" />
                    Ativo (disponivel para uso na criacao de oficios)
                </label>

                <div className="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <Button variant="ghost" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-save">
                        {isEdit ? 'Salvar' : 'Criar Modelo'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ConfirmExcluirModal({ modelo, onClose }) {
    const [submitting, setSubmitting] = useState(false);
    const submit = () => {
        setSubmitting(true);
        router.delete(`/admin/oficios-modelos/${modelo.id}`, {
            onFinish: () => { setSubmitting(false); onClose(); },
        });
    };
    return (
        <Modal show onClose={onClose} title="Excluir modelo">
            <p className="text-sm text-gray-700 mb-4">
                Tem certeza que deseja excluir o modelo <strong>{modelo.nome}</strong>?
            </p>
            <div className="flex justify-end gap-2">
                <Button variant="ghost" onClick={onClose}>Cancelar</Button>
                <Button variant="danger" onClick={submit} loading={submitting} icon="fas fa-trash">Excluir</Button>
            </div>
        </Modal>
    );
}
