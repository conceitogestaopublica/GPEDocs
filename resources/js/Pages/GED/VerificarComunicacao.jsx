/**
 * Verificação pública de memorando, ofício ou circular — destino do QR do PDF.
 */
import { Head } from '@inertiajs/react';

export default function VerificarComunicacao({ valido, tipo, comunicacao = {} }) {
    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
            <Head title={`Verificação de ${tipo}`} />
            <div className="w-full max-w-lg bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                {valido ? (
                    <>
                        <div className="bg-green-50 border-b border-green-100 px-6 py-4 flex items-center gap-3">
                            <i className="fas fa-check-circle text-green-600 text-2xl" />
                            <div>
                                <p className="text-sm font-semibold text-green-800">{tipo} autêntico</p>
                                <p className="text-xs text-green-600">Emitido e registrado no sistema</p>
                            </div>
                        </div>
                        <dl className="px-6 py-5 space-y-3 text-sm">
                            <Linha rotulo="Número" valor={comunicacao.numero} />
                            {comunicacao.confidencial
                                ? <p className="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2"><i className="fas fa-lock mr-1" />Comunicação confidencial: o assunto não é exibido publicamente.</p>
                                : <Linha rotulo="Assunto" valor={comunicacao.assunto} />}
                            <Linha rotulo="Remetente" valor={comunicacao.remetente} />
                            <Linha rotulo="Unidade" valor={comunicacao.unidade} />
                            <Linha rotulo="Órgão" valor={comunicacao.orgao} />
                            <Linha rotulo="Emitido em" valor={comunicacao.emitido_em} />
                            <Linha rotulo="Situação" valor={comunicacao.situacao} />
                        </dl>
                    </>
                ) : (
                    <div className="bg-red-50 px-6 py-6 flex items-center gap-3">
                        <i className="fas fa-times-circle text-red-600 text-2xl" />
                        <div>
                            <p className="text-sm font-semibold text-red-800">{tipo} não encontrado</p>
                            <p className="text-xs text-red-600">O código não corresponde a nenhuma comunicação registrada.</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function Linha({ rotulo, valor }) {
    if (!valor) return null;
    return (
        <div className="flex gap-3">
            <dt className="w-28 shrink-0 text-gray-400 text-xs uppercase tracking-wide pt-0.5">{rotulo}</dt>
            <dd className="text-gray-800">{valor}</dd>
        </div>
    );
}
