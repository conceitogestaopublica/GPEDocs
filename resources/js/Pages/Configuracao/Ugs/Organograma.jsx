/**
 * Organograma de uma UG — arvore com 3 niveis nomeaveis.
 * Os botoes de Adicionar/Editar navegam para tela dedicada (OrganogramaForm).
 */
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export default function Organograma({ ug, arvore }) {
    const [labelsModal, setLabelsModal] = useState(false);

    const labels = { 1: ug.nivel_1_label, 2: ug.nivel_2_label, 3: ug.nivel_3_label };

    const adicionar = (parentId = null) => {
        const url = parentId
            ? `/configuracoes/ugs/${ug.id}/organograma/nodes/create?parent_id=${parentId}`
            : `/configuracoes/ugs/${ug.id}/organograma/nodes/create`;
        router.visit(url);
    };

    return (
        <AdminLayout>
            <Head title={`Organograma — ${ug.nome}`} />
            <PageHeader
                title={`Organograma — ${ug.nome}`}
                subtitle={`UG ${ug.codigo}`}
            >
                <Link href="/configuracoes/ugs"
                    className="text-xs text-gray-500 hover:text-gray-800 flex items-center gap-1">
                    <i className="fas fa-arrow-left" />
                    Voltar para UGs
                </Link>
            </PageHeader>

            <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <i className="fas fa-sitemap text-blue-600" />
                    <div className="text-xs text-blue-800">
                        Niveis: <strong>{labels[1]}</strong> &gt; <strong>{labels[2]}</strong> &gt; <strong>{labels[3]}</strong>
                    </div>
                </div>
                <button onClick={() => setLabelsModal(true)}
                    className="text-[11px] px-3 py-1 rounded-lg text-blue-700 hover:bg-blue-100 transition-colors">
                    <i className="fas fa-edit mr-1" />
                    Renomear niveis
                </button>
            </div>

            <div className="flex justify-end mb-3">
                <Button onClick={() => adicionar(null)} icon="fas fa-plus">
                    Adicionar {labels[1]}
                </Button>
            </div>

            {arvore.length === 0 ? (
                <Card>
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-sitemap text-4xl mb-3 block" />
                        <p className="text-sm">Organograma vazio</p>
                        <p className="text-xs mt-1">
                            Adicione o primeiro {labels[1]} para comecar a montar a estrutura.
                        </p>
                    </div>
                </Card>
            ) : (
                <div className="space-y-2">
                    {arvore.map(node => (
                        <No
                            key={node.id}
                            node={node}
                            ug={ug}
                            labels={labels}
                            onAdicionarFilho={(parentId) => adicionar(parentId)}
                        />
                    ))}
                </div>
            )}

            <LabelsModal show={labelsModal} ug={ug} onClose={() => setLabelsModal(false)} />
        </AdminLayout>
    );
}

function No({ node, ug, labels, onAdicionarFilho, depth = 0 }) {
    const [expandido, setExpandido] = useState(true);
    const filhos = node.filhos_recursivos || [];
    const podeAdicionarFilho = node.nivel < 3;
    const corPorNivel = {
        1: { bg: 'bg-indigo-50', border: 'border-indigo-200', icon: 'text-indigo-600' },
        2: { bg: 'bg-blue-50',   border: 'border-blue-200',   icon: 'text-blue-600' },
        3: { bg: 'bg-cyan-50',   border: 'border-cyan-200',   icon: 'text-cyan-600' },
    }[node.nivel] || {};

    const toggleAtivo = () => {
        router.post(`/configuracoes/ugs/${ug.id}/organograma/nodes/${node.id}/toggle-ativo`);
    };

    const excluir = () => {
        if (! confirm(`Excluir "${node.nome}"? Esta acao nao pode ser desfeita.`)) return;
        router.delete(`/configuracoes/ugs/${ug.id}/organograma/nodes/${node.id}`);
    };

    return (
        <div style={{ marginLeft: depth * 24 + 'px' }}>
            <div className={`${corPorNivel.bg} ${corPorNivel.border} border rounded-xl p-3 mb-2 ${node.ativo ? '' : 'opacity-60'}`}>
                <div className="flex items-center gap-2">
                    {filhos.length > 0 ? (
                        <button onClick={() => setExpandido(!expandido)}
                            className="w-5 h-5 flex items-center justify-center hover:bg-white rounded">
                            <i className={`fas fa-chevron-${expandido ? 'down' : 'right'} text-[10px] text-gray-500`} />
                        </button>
                    ) : (
                        <span className="w-5" />
                    )}
                    <i className={`fas fa-folder ${corPorNivel.icon}`} />
                    <div className="flex-1">
                        <p className="text-sm font-semibold text-gray-800">{node.nome}</p>
                        <p className="text-[10px] text-gray-500 flex items-center gap-2">
                            <span>{labels[node.nivel]}</span>
                            {node.codigo && <><span>·</span><span className="font-mono">{node.codigo}</span></>}
                            <span>·</span>
                            <span>{filhos.length} sub-unidade(s)</span>
                            {node.usuarios_count > 0 && (
                                <>
                                    <span>·</span>
                                    <span><i className="fas fa-users mr-0.5" />{node.usuarios_count}</span>
                                </>
                            )}
                            {! node.ativo && (
                                <span className="ml-2 text-[9px] px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 font-bold">INATIVO</span>
                            )}
                        </p>
                    </div>

                    <div className="flex items-center gap-1">
                        {podeAdicionarFilho && (
                            <button onClick={() => onAdicionarFilho(node.id)}
                                className="text-[10px] px-2 py-1 rounded text-green-700 hover:bg-green-100"
                                title={`Adicionar ${labels[node.nivel + 1]}`}>
                                <i className="fas fa-plus" />
                            </button>
                        )}
                        <Link href={`/configuracoes/ugs/${ug.id}/organograma/nodes/${node.id}/edit`}
                            className="text-[10px] px-2 py-1 rounded text-blue-700 hover:bg-blue-100" title="Editar">
                            <i className="fas fa-edit" />
                        </Link>
                        <button onClick={toggleAtivo}
                            className={`text-[10px] px-2 py-1 rounded ${
                                node.ativo ? 'text-amber-700 hover:bg-amber-100' : 'text-green-700 hover:bg-green-100'
                            }`}
                            title={node.ativo ? 'Inativar' : 'Reativar'}>
                            <i className={`fas fa-${node.ativo ? 'pause' : 'play'}`} />
                        </button>
                        <button onClick={excluir}
                            className="text-[10px] px-2 py-1 rounded text-red-700 hover:bg-red-100" title="Excluir">
                            <i className="fas fa-trash" />
                        </button>
                    </div>
                </div>
            </div>

            {expandido && filhos.map(f => (
                <No
                    key={f.id}
                    node={f}
                    ug={ug}
                    labels={labels}
                    onAdicionarFilho={onAdicionarFilho}
                    onEditar={onEditar}
                    depth={depth + 1}
                />
            ))}
        </div>
    );
}

function LabelsModal({ show, ug, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        nivel_1_label: ug.nivel_1_label,
        nivel_2_label: ug.nivel_2_label,
        nivel_3_label: ug.nivel_3_label,
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/configuracoes/ugs/${ug.id}/organograma/labels`, {
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal show={show} onClose={onClose} title="Renomear niveis do organograma">
            <form onSubmit={submit} className="space-y-3">
                <p className="text-xs text-gray-500">
                    Estes labels sao especificos desta UG e podem ser diferentes em outras.
                </p>
                {[1, 2, 3].map(n => (
                    <div key={n}>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Nivel {n}</label>
                        <input type="text" value={data[`nivel_${n}_label`]}
                            onChange={(e) => setData(`nivel_${n}_label`, e.target.value)}
                            className="ds-input" maxLength={60} />
                        {errors[`nivel_${n}_label`] && <p className="mt-1 text-xs text-red-600">{errors[`nivel_${n}_label`]}</p>}
                    </div>
                ))}
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-save">Salvar</Button>
                </div>
            </form>
        </Modal>
    );
}

