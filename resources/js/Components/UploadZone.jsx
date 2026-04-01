/**
 * UploadZone — Zona de upload com drag-and-drop
 *
 * Props:
 *   - onFilesAdded: function(files) — callback com os arquivos selecionados
 *   - accept: object — tipos MIME aceitos (ex: { 'application/pdf': ['.pdf'] })
 *   - maxSize: number — tamanho máximo em bytes (default: 50MB)
 *   - multiple: boolean — permitir múltiplos arquivos (default: true)
 */
import { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

export default function UploadZone({ onFilesAdded, accept, maxSize = 50 * 1024 * 1024, multiple = true }) {
    const [files, setFiles] = useState([]);

    const onDrop = useCallback((acceptedFiles) => {
        const newFiles = [...files, ...acceptedFiles];
        setFiles(newFiles);
        onFilesAdded?.(newFiles);
    }, [files, onFilesAdded]);

    const removeFile = (index) => {
        const updated = files.filter((_, i) => i !== index);
        setFiles(updated);
        onFilesAdded?.(updated);
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept,
        maxSize,
        multiple,
    });

    return (
        <div>
            {/* Zona de drop */}
            <div
                {...getRootProps()}
                className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors
                    ${isDragActive
                        ? 'border-blue-400 bg-blue-50'
                        : 'border-gray-300 hover:border-blue-300 hover:bg-gray-50'
                    }`}
            >
                <input {...getInputProps()} />
                <i className={`fas fa-cloud-upload-alt text-3xl mb-3 block ${isDragActive ? 'text-blue-500' : 'text-gray-400'}`} />
                {isDragActive ? (
                    <p className="text-blue-600 font-medium">Solte os arquivos aqui...</p>
                ) : (
                    <>
                        <p className="text-gray-600 font-medium">Arraste arquivos aqui ou clique para selecionar</p>
                        <p className="text-gray-400 text-sm mt-1">Tamanho máximo: {formatSize(maxSize)}</p>
                    </>
                )}
            </div>

            {/* Lista de arquivos adicionados */}
            {files.length > 0 && (
                <div className="mt-4 space-y-2">
                    {files.map((file, index) => (
                        <div key={`${file.name}-${index}`} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <i className="fas fa-file text-gray-400" />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-700 truncate">{file.name}</p>
                                <p className="text-xs text-gray-400">{formatSize(file.size)}</p>
                                {/* Placeholder para barra de progresso */}
                                <div className="mt-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                                    <div className="h-full bg-blue-500 rounded-full" style={{ width: '0%' }} />
                                </div>
                            </div>
                            <button
                                onClick={() => removeFile(index)}
                                className="w-7 h-7 rounded-md hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors"
                            >
                                <i className="fas fa-times text-xs" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
