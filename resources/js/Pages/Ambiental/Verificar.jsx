/** Verificação pública da licença ambiental (QR do PDF). */
import { Head } from '@inertiajs/react';

export default function Verificar({ codigo, licenca: l }) {
    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <Head title="Verificação de licença ambiental" />
            <div className="mx-auto max-w-lg rounded-2xl bg-white p-6 shadow">
                <p className="text-xs text-gray-500">Verificação de licença ambiental · código {codigo}</p>
                {l ? (
                    <>
                        <p className={`mt-3 flex items-center gap-2 text-lg font-semibold ${l.valida ? 'text-emerald-700' : 'text-amber-700'}`}>
                            <i className={`fas ${l.valida ? 'fa-circle-check' : 'fa-circle-exclamation'}`} />
                            {l.valida ? 'Licença válida' : `Licença ${l.situacao.toLowerCase()}`}
                        </p>
                        <dl className="mt-3 grid grid-cols-3 gap-y-1 text-sm">
                            <dt className="text-gray-500">Número</dt><dd className="col-span-2 font-semibold">{l.numero}</dd>
                            <dt className="text-gray-500">Modalidade</dt><dd className="col-span-2">{l.modalidade}</dd>
                            <dt className="text-gray-500">Situação</dt><dd className="col-span-2">{l.situacao}</dd>
                            <dt className="text-gray-500">Empreendedor</dt><dd className="col-span-2">{l.empreendedor}</dd>
                            {l.empreendimento && <><dt className="text-gray-500">Empreendimento</dt><dd className="col-span-2">{l.empreendimento}{l.endereco ? `, ${l.endereco}` : ''}</dd></>}
                            <dt className="text-gray-500">Atividade</dt><dd className="col-span-2">{l.atividade} (classe {l.classe})</dd>
                            <dt className="text-gray-500">Emitida em</dt><dd className="col-span-2">{l.emitida_em}</dd>
                            <dt className="text-gray-500">Válida até</dt><dd className="col-span-2">{l.valida_ate}</dd>
                            <dt className="text-gray-500">Assinatura</dt><dd className="col-span-2">{l.assinada ? 'assinada digitalmente' : 'aguardando assinatura'}</dd>
                            {l.orgao && <><dt className="text-gray-500">Órgão</dt><dd className="col-span-2">{l.orgao}</dd></>}
                        </dl>
                        {l.condicionantes.length > 0 && (
                            <div className="mt-3 text-sm">
                                <p className="text-gray-500">Condicionantes</p>
                                <ol className="ml-5 list-decimal">{l.condicionantes.map((c, i) => <li key={i}>{c}</li>)}</ol>
                            </div>
                        )}
                    </>
                ) : <p className="mt-3 flex items-center gap-2 text-lg font-semibold text-red-700"><i className="fas fa-circle-xmark" /> Licença não encontrada</p>}
            </div>
        </div>
    );
}
