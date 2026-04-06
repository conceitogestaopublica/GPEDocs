/**
 * Pagina publica de verificacao de documento — GED
 */
import { Head } from '@inertiajs/react';

export default function Verificar({ documento, valido }) {
    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title={valido ? 'Documento Verificado' : 'Verificacao'} />

            <div className="w-full max-w-lg">
                {/* Header */}
                <div className="text-center mb-6">
                    <div className="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center mx-auto shadow-lg shadow-blue-200 mb-3">
                        <i className="fas fa-archive text-white text-xl" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-800">GED - Verificacao de Documento</h1>
                    <p className="text-sm text-gray-500">Conceito Gestao Publica</p>
                </div>

                <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    {valido ? (
                        <>
                            {/* Valido */}
                            <div className="bg-green-50 border-b border-green-100 px-6 py-4 flex items-center gap-3">
                                <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <i className="fas fa-check-circle text-green-600 text-lg" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-green-800">Documento Autentico</p>
                                    <p className="text-xs text-green-600">Este documento foi registrado e verificado no sistema GED</p>
                                </div>
                            </div>

                            <div className="px-6 py-5 space-y-4">
                                <InfoRow label="Nome" value={documento.nome} />
                                <InfoRow label="Tipo Documental" value={documento.tipo_documental || '-'} />
                                <InfoRow label="Autor" value={documento.autor} />
                                <InfoRow label="Status" value={documento.status} />
                                <InfoRow label="Classificacao" value={documento.classificacao} />
                                <InfoRow label="Versao" value={`v${documento.versao}`} />
                                <InfoRow label="Criado em" value={documento.criado_em} />
                                <InfoRow label="Atualizado em" value={documento.atualizado_em} />

                                {documento.hash && (
                                    <div>
                                        <p className="text-[10px] text-gray-400 uppercase font-semibold tracking-wide mb-1">Hash SHA-256</p>
                                        <p className="text-[11px] text-gray-600 font-mono bg-gray-50 rounded-lg px-3 py-2 break-all">{documento.hash}</p>
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            {/* Invalido */}
                            <div className="bg-red-50 border-b border-red-100 px-6 py-4 flex items-center gap-3">
                                <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                    <i className="fas fa-times-circle text-red-600 text-lg" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-red-800">Documento Nao Encontrado</p>
                                    <p className="text-xs text-red-600">O codigo informado nao corresponde a nenhum documento registrado</p>
                                </div>
                            </div>

                            <div className="px-6 py-8 text-center text-gray-400">
                                <i className="fas fa-search text-3xl mb-3 block" />
                                <p className="text-sm">Verifique se o QR Code foi lido corretamente</p>
                            </div>
                        </>
                    )}
                </div>

                <p className="text-center text-[10px] text-gray-400 mt-4">
                    GED — Gestao Eletronica de Documentos — Conceito Gestao Publica
                </p>
            </div>
        </div>
    );
}

function InfoRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-xs text-gray-500 font-medium shrink-0">{label}</span>
            <span className="text-sm text-gray-800 text-right">{value || '-'}</span>
        </div>
    );
}
