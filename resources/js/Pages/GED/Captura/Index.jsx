/**
 * Captura de Documentos — GED
 *
 * Upload de arquivos com drag-and-drop, metadados dinamicos e selecao de pasta destino.
 */
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function Captura({ tipos_documentais, pastas }) {
    const [activeTab, setActiveTab] = useState('upload');
    const [files, setFiles] = useState([]);
    const [dragActive, setDragActive] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        tipo_documental_id: '',
        pasta_id: '',
        classificacao: 'publico',
        descricao: '',
        files: [],
    });

    const tipos = tipos_documentais || [];
    const pastaList = pastas || [];

    const handleDrop = useCallback((e) => {
        e.preventDefault();
        setDragActive(false);
        const droppedFiles = Array.from(e.dataTransfer.files);
        addFiles(droppedFiles);
    }, []);

    const handleDragOver = (e) => { e.preventDefault(); setDragActive(true); };
    const handleDragLeave = () => setDragActive(false);

    const handleFileInput = (e) => {
        const selectedFiles = Array.from(e.target.files);
        addFiles(selectedFiles);
    };

    const addFiles = (newFiles) => {
        const combined = [...files, ...newFiles];
        setFiles(combined);
        setData('files', combined);
    };

    const removeFile = (index) => {
        const updated = files.filter((_, i) => i !== index);
        setFiles(updated);
        setData('files', updated);
    };

    const submit = (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('tipo_documental_id', data.tipo_documental_id);
        formData.append('pasta_id', data.pasta_id);
        formData.append('classificacao', data.classificacao);
        formData.append('descricao', data.descricao);
        files.forEach((file, i) => formData.append(`files[${i}]`, file));

        post('/capturar/upload', {
            data: formData,
            forceFormData: true,
            onSuccess: () => {
                reset();
                setFiles([]);
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Capturar Documento" />

            <PageHeader title="Capturar Documento" subtitle="Digitalizar ou fazer upload de documentos" />

            {/* Tabs de fonte */}
            <div className="flex gap-2 mb-6">
                {[
                    { key: 'upload', label: 'Upload de Arquivo', icon: 'fas fa-upload' },
                    { key: 'scanner', label: 'Scanner', icon: 'fas fa-print' },
                    { key: 'camera', label: 'Camera', icon: 'fas fa-camera' },
                ].map(tab => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors
                            ${activeTab === tab.key
                                ? 'bg-blue-600 text-white shadow-md'
                                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                    >
                        <i className={`${tab.icon} text-xs`} />
                        {tab.label}
                    </button>
                ))}
            </div>

            <form onSubmit={submit}>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Coluna esquerda: Area de upload */}
                    <div className="lg:col-span-2">
                        <Card title="Arquivos">
                            {activeTab === 'upload' && (
                                <>
                                    {/* Dropzone */}
                                    <div
                                        onDrop={handleDrop}
                                        onDragOver={handleDragOver}
                                        onDragLeave={handleDragLeave}
                                        className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer
                                            ${dragActive ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-blue-300'}`}
                                        onClick={() => document.getElementById('fileInput').click()}
                                    >
                                        <i className={`fas fa-cloud-upload-alt text-4xl mb-3 block ${dragActive ? 'text-blue-500' : 'text-gray-300'}`} />
                                        <p className="text-sm font-medium text-gray-600">
                                            Arraste e solte seus arquivos aqui
                                        </p>
                                        <p className="text-xs text-gray-400 mt-1">ou clique para selecionar</p>
                                        <p className="text-[10px] text-gray-400 mt-3">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG — Max 50MB por arquivo</p>
                                        <input
                                            id="fileInput"
                                            type="file"
                                            multiple
                                            onChange={handleFileInput}
                                            className="hidden"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.bmp,.tiff"
                                        />
                                    </div>

                                    {/* Lista de arquivos */}
                                    {files.length > 0 && (
                                        <div className="mt-4 space-y-2">
                                            {files.map((file, idx) => (
                                                <div key={idx} className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <i className={`${getFileIcon(file.type)} text-lg`} />
                                                        <div>
                                                            <p className="text-sm font-medium text-gray-700">{file.name}</p>
                                                            <p className="text-xs text-gray-400">{formatBytes(file.size)}</p>
                                                        </div>
                                                    </div>
                                                    <button type="button" onClick={() => removeFile(idx)}
                                                        className="text-red-400 hover:text-red-600 transition-colors">
                                                        <i className="fas fa-times" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {errors.files && <p className="mt-2 text-xs text-red-600">{errors.files}</p>}
                                </>
                            )}

                            {activeTab === 'scanner' && (
                                <div className="py-12 text-center text-gray-400">
                                    <i className="fas fa-print text-4xl mb-3 block" />
                                    <p className="text-sm font-medium">Integracao com Scanner</p>
                                    <p className="text-xs mt-1">Funcionalidade em desenvolvimento</p>
                                </div>
                            )}

                            {activeTab === 'camera' && (
                                <div className="py-12 text-center text-gray-400">
                                    <i className="fas fa-camera text-4xl mb-3 block" />
                                    <p className="text-sm font-medium">Captura por Camera</p>
                                    <p className="text-xs mt-1">Funcionalidade em desenvolvimento</p>
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Coluna direita: Metadados */}
                    <div>
                        <Card title="Metadados">
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Tipo Documental</label>
                                    <select
                                        value={data.tipo_documental_id}
                                        onChange={(e) => setData('tipo_documental_id', e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="">Selecionar tipo...</option>
                                        {tipos.map(t => (
                                            <option key={t.id} value={t.id}>{t.nome}</option>
                                        ))}
                                    </select>
                                    {errors.tipo_documental_id && <p className="mt-1 text-xs text-red-600">{errors.tipo_documental_id}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Pasta de Destino</label>
                                    <select
                                        value={data.pasta_id}
                                        onChange={(e) => setData('pasta_id', e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="">Raiz</option>
                                        {pastaList.map(p => (
                                            <option key={p.id} value={p.id}>{p.path_display || p.nome}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Classificacao</label>
                                    <select
                                        value={data.classificacao}
                                        onChange={(e) => setData('classificacao', e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="publico">Publico</option>
                                        <option value="interno">Interno</option>
                                        <option value="confidencial">Confidencial</option>
                                        <option value="restrito">Restrito</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                                    <textarea
                                        value={data.descricao}
                                        onChange={(e) => setData('descricao', e.target.value)}
                                        className="ds-input !h-auto"
                                        rows={3}
                                        placeholder="Descricao opcional do documento..."
                                    />
                                </div>
                            </div>
                        </Card>

                        <div className="mt-4">
                            <Button
                                type="submit"
                                loading={processing}
                                icon="fas fa-save"
                                className="w-full justify-center"
                                disabled={files.length === 0}
                            >
                                Processar e Salvar
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
