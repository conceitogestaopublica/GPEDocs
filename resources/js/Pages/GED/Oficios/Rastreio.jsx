/**
 * Pagina publica de rastreio de oficio — GED
 *
 * Exibida quando o destinatario externo acessa o link de rastreio.
 * Nao utiliza AdminLayout (pagina publica, sem autenticacao).
 */
import { Head } from '@inertiajs/react';

export default function Rastreio({ valido, oficio }) {
    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title={valido ? 'Oficio Recebido' : 'Rastreio de Oficio'} />

            <div className="w-full max-w-lg">
                {/* Header */}
                <div className="text-center mb-6">
                    <div className="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center mx-auto shadow-lg shadow-blue-200 mb-3">
                        <i className="fas fa-file-alt text-white text-xl" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-800">GED - Rastreio de Oficio</h1>
                    <p className="text-sm text-gray-500">Conceito Gestao Publica</p>
                </div>

                <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    {valido && oficio ? (
                        <>
                            {/* Recebido com sucesso */}
                            <div className="bg-green-50 border-b border-green-100 px-6 py-4 flex items-center gap-3">
                                <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <i className="fas fa-check-circle text-green-600 text-lg" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-green-800">Oficio Recebido com Sucesso</p>
                                    <p className="text-xs text-green-600">O recebimento foi registrado no sistema</p>
                                </div>
                            </div>

                            <div className="px-6 py-5 space-y-4">
                                <InfoRow label="Numero" value={oficio.numero} />
                                <InfoRow label="Assunto" value={oficio.assunto} />
                                {oficio.lido_em && (
                                    <InfoRow label="Recebido em" value={oficio.lido_em} />
                                )}

                                <div className="bg-blue-50 rounded-lg p-4 mt-4">
                                    <div className="flex items-start gap-2">
                                        <i className="fas fa-info-circle text-blue-500 mt-0.5" />
                                        <div className="text-xs text-blue-700 leading-relaxed">
                                            <p className="font-medium mb-1">Confirmacao de Recebimento</p>
                                            <p>
                                                A data e hora do seu acesso foram registradas automaticamente.
                                                O remetente sera notificado do recebimento deste oficio.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    ) : (
                        <>
                            {/* Nao encontrado */}
                            <div className="bg-red-50 border-b border-red-100 px-6 py-4 flex items-center gap-3">
                                <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                    <i className="fas fa-times-circle text-red-600 text-lg" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-red-800">Oficio Nao Encontrado</p>
                                    <p className="text-xs text-red-600">O codigo informado nao corresponde a nenhum oficio registrado</p>
                                </div>
                            </div>

                            <div className="px-6 py-8 text-center text-gray-400">
                                <i className="fas fa-search text-3xl mb-3 block" />
                                <p className="text-sm">Verifique se o link de rastreio esta correto</p>
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
