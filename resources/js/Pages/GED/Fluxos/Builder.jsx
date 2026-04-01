/**
 * Construtor de Fluxos de Trabalho — GED
 *
 * Canvas com react-flow para construir fluxos visuais de aprovacao.
 */
import { Head, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import Button from '../../../Components/Button';

// Node types for the palette
const NODE_TYPES = [
    { type: 'inicio', label: 'Inicio', icon: 'fas fa-play', color: 'bg-green-500' },
    { type: 'aprovacao', label: 'Aprovacao', icon: 'fas fa-check-circle', color: 'bg-blue-500' },
    { type: 'revisao', label: 'Revisao', icon: 'fas fa-search', color: 'bg-purple-500' },
    { type: 'notificacao', label: 'Notificacao', icon: 'fas fa-bell', color: 'bg-amber-500' },
    { type: 'condicao', label: 'Condicao', icon: 'fas fa-code-branch', color: 'bg-orange-500' },
    { type: 'assinatura', label: 'Assinatura', icon: 'fas fa-pen-nib', color: 'bg-indigo-500' },
    { type: 'arquivar', label: 'Arquivar', icon: 'fas fa-archive', color: 'bg-teal-500' },
    { type: 'fim', label: 'Fim', icon: 'fas fa-stop', color: 'bg-red-500' },
];

export default function Builder({ fluxo }) {
    const isEdit = !!fluxo?.id;
    const [nome, setNome] = useState(fluxo?.nome || '');
    const [descricao, setDescricao] = useState(fluxo?.descricao || '');
    const [nodes, setNodes] = useState(fluxo?.definicao?.nodes || [
        { id: '1', type: 'inicio', position: { x: 250, y: 50 }, data: { label: 'Inicio' } },
    ]);
    const [edges, setEdges] = useState(fluxo?.definicao?.edges || []);
    const [selectedNode, setSelectedNode] = useState(null);
    const [saving, setSaving] = useState(false);

    const addNode = (type) => {
        const nodeType = NODE_TYPES.find(n => n.type === type);
        const newNode = {
            id: String(Date.now()),
            type: type,
            position: { x: 250, y: (nodes.length * 120) + 50 },
            data: { label: nodeType?.label || type, responsavel: '', prazo: '', mensagem: '' },
        };
        setNodes([...nodes, newNode]);
    };

    const removeNode = (nodeId) => {
        setNodes(nodes.filter(n => n.id !== nodeId));
        setEdges(edges.filter(e => e.source !== nodeId && e.target !== nodeId));
        if (selectedNode?.id === nodeId) setSelectedNode(null);
    };

    const updateNodeData = (nodeId, key, value) => {
        setNodes(nodes.map(n => n.id === nodeId ? { ...n, data: { ...n.data, [key]: value } } : n));
        if (selectedNode?.id === nodeId) {
            setSelectedNode({ ...selectedNode, data: { ...selectedNode.data, [key]: value } });
        }
    };

    const save = () => {
        setSaving(true);
        const definicao = { nodes, edges };
        const payload = { nome, descricao, definicao };

        if (isEdit) {
            router.put(`/fluxos/${fluxo.id}`, payload, {
                onFinish: () => setSaving(false),
            });
        } else {
            router.post('/fluxos', payload, {
                onFinish: () => setSaving(false),
            });
        }
    };

    return (
        <AdminLayout>
            <Head title={isEdit ? `Editar Fluxo: ${fluxo.nome}` : 'Novo Fluxo'} />

            {/* Toolbar */}
            <div className="bg-white rounded-xl border border-gray-200 px-5 py-3 mb-4 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <input
                        type="text"
                        value={nome}
                        onChange={(e) => setNome(e.target.value)}
                        placeholder="Nome do fluxo..."
                        className="text-lg font-semibold text-gray-800 border-none outline-none bg-transparent placeholder-gray-300"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" href="/fluxos">Cancelar</Button>
                    <Button icon="fas fa-save" onClick={save} loading={saving}>Salvar Fluxo</Button>
                </div>
            </div>

            <div className="flex gap-4" style={{ height: 'calc(100vh - 230px)' }}>
                {/* Paleta de nos */}
                <div className="w-56 shrink-0 bg-white rounded-xl border border-gray-200 p-4 overflow-y-auto">
                    <p className="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Componentes</p>
                    <div className="space-y-2">
                        {NODE_TYPES.map(nt => (
                            <button
                                key={nt.type}
                                onClick={() => addNode(nt.type)}
                                className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-200 text-left hover:border-blue-300 hover:bg-blue-50 transition-all group"
                            >
                                <div className={`w-8 h-8 ${nt.color} rounded-lg flex items-center justify-center`}>
                                    <i className={`${nt.icon} text-white text-xs`} />
                                </div>
                                <span className="text-sm text-gray-700 font-medium">{nt.label}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Canvas area */}
                <div className="flex-1 bg-white rounded-xl border border-gray-200 overflow-hidden relative">
                    <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9IiNlNWU3ZWIiLz48L3N2Zz4=')] p-8 overflow-y-auto">
                        {/* Simplified node rendering (without react-flow for initial version) */}
                        <div className="space-y-4 max-w-lg mx-auto">
                            {nodes.map((node, idx) => {
                                const nt = NODE_TYPES.find(n => n.type === node.type) || NODE_TYPES[0];
                                return (
                                    <div key={node.id}>
                                        <div
                                            onClick={() => setSelectedNode(node)}
                                            className={`flex items-center gap-4 bg-white rounded-xl border-2 p-4 cursor-pointer transition-all shadow-sm
                                                ${selectedNode?.id === node.id ? 'border-blue-500 shadow-blue-100' : 'border-gray-200 hover:border-blue-300'}`}
                                        >
                                            <div className={`w-10 h-10 ${nt.color} rounded-xl flex items-center justify-center shrink-0`}>
                                                <i className={`${nt.icon} text-white`} />
                                            </div>
                                            <div className="flex-1">
                                                <p className="text-sm font-semibold text-gray-700">{node.data.label}</p>
                                                {node.data.responsavel && <p className="text-xs text-gray-400">Responsavel: {node.data.responsavel}</p>}
                                            </div>
                                            {node.type !== 'inicio' && (
                                                <button onClick={(e) => { e.stopPropagation(); removeNode(node.id); }}
                                                    className="text-gray-300 hover:text-red-500 transition-colors">
                                                    <i className="fas fa-times text-xs" />
                                                </button>
                                            )}
                                        </div>
                                        {idx < nodes.length - 1 && (
                                            <div className="flex justify-center py-1">
                                                <i className="fas fa-arrow-down text-gray-300 text-xs" />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Painel de configuracao do no selecionado */}
                {selectedNode && (
                    <div className="w-72 shrink-0 bg-white rounded-xl border border-gray-200 p-5 overflow-y-auto">
                        <div className="flex items-center justify-between mb-4">
                            <p className="text-sm font-bold text-gray-700">Configuracao</p>
                            <button onClick={() => setSelectedNode(null)} className="text-gray-400 hover:text-gray-600">
                                <i className="fas fa-times text-xs" />
                            </button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Nome da Etapa</label>
                                <input type="text" value={selectedNode.data.label}
                                    onChange={(e) => updateNodeData(selectedNode.id, 'label', e.target.value)}
                                    className="ds-input" />
                            </div>

                            {['aprovacao', 'revisao', 'assinatura'].includes(selectedNode.type) && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Responsavel</label>
                                        <input type="text" value={selectedNode.data.responsavel || ''}
                                            onChange={(e) => updateNodeData(selectedNode.id, 'responsavel', e.target.value)}
                                            className="ds-input" placeholder="Nome ou grupo" />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Prazo (dias)</label>
                                        <input type="number" value={selectedNode.data.prazo || ''}
                                            onChange={(e) => updateNodeData(selectedNode.id, 'prazo', e.target.value)}
                                            className="ds-input" placeholder="Ex: 5" />
                                    </div>
                                </>
                            )}

                            {selectedNode.type === 'notificacao' && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Mensagem</label>
                                    <textarea value={selectedNode.data.mensagem || ''}
                                        onChange={(e) => updateNodeData(selectedNode.id, 'mensagem', e.target.value)}
                                        className="ds-input !h-auto" rows={3} placeholder="Texto da notificacao..." />
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
