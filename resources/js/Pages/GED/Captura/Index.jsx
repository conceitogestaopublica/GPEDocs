/**
 * Captura de Documentos — GED
 *
 * Upload de arquivos com drag-and-drop, captura por camera e metadados dinamicos.
 */
import { Head, useForm } from '@inertiajs/react';
import { useState, useCallback, useRef, useEffect } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function Captura({ tipos_documentais, pastas }) {
    const [activeTab, setActiveTab] = useState('upload');
    const [files, setFiles] = useState([]);
    const [dragActive, setDragActive] = useState(false);

    // Camera state
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState(null);
    const [capturedPages, setCapturedPages] = useState([]);
    const [selectedPage, setSelectedPage] = useState(null);
    const [facingMode, setFacingMode] = useState('environment');
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        tipo_documental_id: '',
        pasta_id: '',
        classificacao: 'publico',
        descricao: '',
        files: [],
    });

    const tipos = tipos_documentais || [];
    const pastaList = pastas || [];

    // ── Upload handlers ──
    const handleDrop = useCallback((e) => {
        e.preventDefault();
        setDragActive(false);
        addFiles(Array.from(e.dataTransfer.files));
    }, [files]);

    const handleDragOver = (e) => { e.preventDefault(); setDragActive(true); };
    const handleDragLeave = () => setDragActive(false);

    const handleFileInput = (e) => {
        addFiles(Array.from(e.target.files));
        e.target.value = '';
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

    // ── Camera handlers ──
    const startCamera = async () => {
        setCameraError(null);
        try {
            const constraints = {
                video: {
                    facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
            };
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }
            setCameraActive(true);
        } catch (err) {
            setCameraError(
                err.name === 'NotAllowedError'
                    ? 'Permissao de camera negada. Habilite nas configuracoes do navegador.'
                    : err.name === 'NotFoundError'
                    ? 'Nenhuma camera encontrada neste dispositivo.'
                    : 'Erro ao acessar camera: ' + err.message
            );
        }
    };

    const stopCamera = () => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach(track => track.stop());
            streamRef.current = null;
        }
        setCameraActive(false);
    };

    const switchCamera = () => {
        stopCamera();
        setFacingMode(prev => prev === 'environment' ? 'user' : 'environment');
    };

    useEffect(() => {
        if (activeTab === 'camera' && cameraActive) {
            stopCamera();
            startCamera();
        }
    }, [facingMode]);

    useEffect(() => {
        return () => stopCamera();
    }, []);

    useEffect(() => {
        if (activeTab !== 'camera') {
            stopCamera();
        }
    }, [activeTab]);

    const capturePhoto = () => {
        if (!videoRef.current || !canvasRef.current) return;

        const video = videoRef.current;
        const canvas = canvasRef.current;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);

        canvas.toBlob((blob) => {
            if (!blob) return;
            const pageNum = capturedPages.length + 1;
            const page = {
                id: Date.now(),
                blob,
                url: URL.createObjectURL(blob),
                name: `pagina-${String(pageNum).padStart(3, '0')}.jpg`,
                rotation: 0,
            };
            setCapturedPages(prev => [...prev, page]);
            setSelectedPage(page.id);
        }, 'image/jpeg', 0.92);
    };

    const removePage = (id) => {
        setCapturedPages(prev => {
            const updated = prev.filter(p => p.id !== id);
            if (selectedPage === id) {
                setSelectedPage(updated.length > 0 ? updated[updated.length - 1].id : null);
            }
            return updated;
        });
    };

    const rotatePage = (id, direction) => {
        setCapturedPages(prev => prev.map(p =>
            p.id === id ? { ...p, rotation: (p.rotation + direction + 360) % 360 } : p
        ));
    };

    const movePage = (id, offset) => {
        setCapturedPages(prev => {
            const idx = prev.findIndex(p => p.id === id);
            const newIdx = idx + offset;
            if (newIdx < 0 || newIdx >= prev.length) return prev;
            const copy = [...prev];
            [copy[idx], copy[newIdx]] = [copy[newIdx], copy[idx]];
            return copy;
        });
    };

    const addCapturedToFiles = () => {
        if (capturedPages.length === 0) return;

        const newFiles = capturedPages.map((page, i) => {
            const file = new File([page.blob], page.name, { type: 'image/jpeg' });
            return file;
        });

        addFiles(newFiles);
        setCapturedPages([]);
        setSelectedPage(null);
        stopCamera();
        setActiveTab('upload');
    };

    const selectedPageData = capturedPages.find(p => p.id === selectedPage);

    // ── Submit ──
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

            {/* Tabs */}
            <div className="flex gap-2 mb-6">
                {[
                    { key: 'upload', label: 'Upload de Arquivo', icon: 'fas fa-upload' },
                    { key: 'camera', label: 'Digitalizar', icon: 'fas fa-camera' },
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
                    {/* Coluna esquerda */}
                    <div className="lg:col-span-2">
                        {/* ── Tab: Upload ── */}
                        {activeTab === 'upload' && (
                            <Card title="Arquivos">
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
                            </Card>
                        )}

                        {/* ── Tab: Camera / Digitalizar ── */}
                        {activeTab === 'camera' && (
                            <div className="space-y-4">
                                {/* Visualizador principal */}
                                <Card title="Digitalizar" padding={false}>
                                    <div className="relative bg-gray-900 rounded-b-xl overflow-hidden" style={{ minHeight: 400 }}>
                                        {/* Camera feed */}
                                        {cameraActive && !selectedPageData && (
                                            <video
                                                ref={videoRef}
                                                autoPlay
                                                playsInline
                                                muted
                                                className="w-full h-full object-contain"
                                                style={{ minHeight: 400, maxHeight: 500 }}
                                            />
                                        )}

                                        {/* Preview de pagina selecionada */}
                                        {selectedPageData && (
                                            <div className="flex items-center justify-center p-4" style={{ minHeight: 400 }}>
                                                <img
                                                    src={selectedPageData.url}
                                                    alt={selectedPageData.name}
                                                    className="max-h-[460px] object-contain shadow-lg rounded"
                                                    style={{ transform: `rotate(${selectedPageData.rotation}deg)` }}
                                                />
                                            </div>
                                        )}

                                        {/* Estado inicial (camera desligada) */}
                                        {!cameraActive && !selectedPageData && (
                                            <div className="flex flex-col items-center justify-center text-gray-400 py-20">
                                                <i className="fas fa-camera text-5xl mb-4" />
                                                <p className="text-sm font-medium">Camera desligada</p>
                                                <p className="text-xs mt-1 text-gray-500">Clique em "Iniciar Camera" para comecar a digitalizar</p>
                                            </div>
                                        )}

                                        {/* Erro */}
                                        {cameraError && (
                                            <div className="absolute inset-0 flex flex-col items-center justify-center bg-gray-900/90 text-white p-8">
                                                <i className="fas fa-exclamation-triangle text-3xl text-yellow-400 mb-3" />
                                                <p className="text-sm text-center">{cameraError}</p>
                                                <button
                                                    type="button"
                                                    onClick={startCamera}
                                                    className="mt-4 px-4 py-2 bg-blue-600 rounded-lg text-sm hover:bg-blue-700"
                                                >
                                                    Tentar novamente
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {/* Canvas oculto para captura */}
                                    <canvas ref={canvasRef} className="hidden" />
                                </Card>

                                {/* Barra de ferramentas */}
                                <Card padding={false}>
                                    <div className="flex flex-wrap items-center gap-2 p-3">
                                        {/* Controles de camera */}
                                        {!cameraActive ? (
                                            <button
                                                type="button"
                                                onClick={startCamera}
                                                className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors"
                                            >
                                                <i className="fas fa-video" />
                                                Iniciar Camera
                                            </button>
                                        ) : (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={capturePhoto}
                                                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                                                >
                                                    <i className="fas fa-camera" />
                                                    Capturar
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={switchCamera}
                                                    className="flex items-center gap-2 px-3 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition-colors"
                                                    title="Alternar camera"
                                                >
                                                    <i className="fas fa-sync-alt" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={stopCamera}
                                                    className="flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors"
                                                    title="Desligar camera"
                                                >
                                                    <i className="fas fa-video-slash" />
                                                </button>
                                            </>
                                        )}

                                        {/* Separador */}
                                        {selectedPageData && (
                                            <>
                                                <div className="w-px h-8 bg-gray-200 mx-1" />

                                                {/* Ferramentas de imagem */}
                                                <button type="button" onClick={() => rotatePage(selectedPage, -90)}
                                                    className="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors" title="Rotacionar esquerda">
                                                    <i className="fas fa-undo" />
                                                </button>
                                                <button type="button" onClick={() => rotatePage(selectedPage, 90)}
                                                    className="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors" title="Rotacionar direita">
                                                    <i className="fas fa-redo" />
                                                </button>

                                                <div className="w-px h-8 bg-gray-200 mx-1" />

                                                <button type="button" onClick={() => movePage(selectedPage, -1)}
                                                    className="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors" title="Mover para antes">
                                                    <i className="fas fa-arrow-left" />
                                                </button>
                                                <button type="button" onClick={() => movePage(selectedPage, 1)}
                                                    className="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors" title="Mover para depois">
                                                    <i className="fas fa-arrow-right" />
                                                </button>

                                                <div className="w-px h-8 bg-gray-200 mx-1" />

                                                <button type="button" onClick={() => removePage(selectedPage)}
                                                    className="p-2 bg-red-50 rounded-lg hover:bg-red-100 text-red-600 transition-colors" title="Excluir pagina">
                                                    <i className="fas fa-trash" />
                                                </button>

                                                {/* Voltar para camera ao vivo */}
                                                {cameraActive && (
                                                    <button type="button" onClick={() => setSelectedPage(null)}
                                                        className="flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg text-sm hover:bg-blue-100 transition-colors ml-auto"
                                                    >
                                                        <i className="fas fa-video" />
                                                        Voltar ao vivo
                                                    </button>
                                                )}
                                            </>
                                        )}

                                        {/* Info de paginas */}
                                        <div className="ml-auto text-sm text-gray-500">
                                            Pagina: {selectedPageData
                                                ? `${capturedPages.findIndex(p => p.id === selectedPage) + 1} de ${capturedPages.length}`
                                                : `${capturedPages.length} capturada(s)`
                                            }
                                        </div>
                                    </div>
                                </Card>

                                {/* Thumbnails das paginas capturadas */}
                                {capturedPages.length > 0 && (
                                    <Card title={`Paginas capturadas (${capturedPages.length})`}
                                        actions={
                                            <button
                                                type="button"
                                                onClick={addCapturedToFiles}
                                                className="flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700 transition-colors"
                                            >
                                                <i className="fas fa-check" />
                                                Adicionar aos arquivos
                                            </button>
                                        }
                                    >
                                        <div className="flex gap-3 overflow-x-auto pb-2">
                                            {capturedPages.map((page, idx) => (
                                                <button
                                                    key={page.id}
                                                    type="button"
                                                    onClick={() => setSelectedPage(page.id)}
                                                    className={`relative flex-shrink-0 rounded-lg overflow-hidden border-2 transition-all
                                                        ${selectedPage === page.id
                                                            ? 'border-blue-500 shadow-md ring-2 ring-blue-200'
                                                            : 'border-gray-200 hover:border-gray-300'}`}
                                                    style={{ width: 100, height: 130 }}
                                                >
                                                    <img
                                                        src={page.url}
                                                        alt={page.name}
                                                        className="w-full h-full object-cover"
                                                        style={{ transform: `rotate(${page.rotation}deg)` }}
                                                    />
                                                    <span className="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[10px] text-center py-0.5">
                                                        {idx + 1}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </Card>
                                )}
                            </div>
                        )}
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

                        {/* Resumo dos arquivos prontos */}
                        {files.length > 0 && (
                            <Card title={`Arquivos prontos (${files.length})`} className="mt-4">
                                <div className="space-y-1.5 max-h-48 overflow-y-auto">
                                    {files.map((file, idx) => (
                                        <div key={idx} className="flex items-center justify-between text-xs bg-gray-50 rounded px-3 py-2">
                                            <div className="flex items-center gap-2 truncate">
                                                <i className={`${getFileIcon(file.type)}`} />
                                                <span className="truncate">{file.name}</span>
                                            </div>
                                            <button type="button" onClick={() => removeFile(idx)}
                                                className="text-red-400 hover:text-red-600 ml-2 flex-shrink-0">
                                                <i className="fas fa-times text-[10px]" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

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
