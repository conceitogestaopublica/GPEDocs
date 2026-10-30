/** Verificação pública do certificado do EAD (QR do PDF). */
import { Head } from '@inertiajs/react';

export default function Verificar({ valido, codigo, certificado: c }) {
    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <Head title="Verificação de certificado" />
            <div className="mx-auto max-w-lg rounded-2xl bg-white p-6 shadow">
                <p className="text-xs text-gray-500">Verificação de certificado · código {codigo}</p>
                {valido ? (
                    <>
                        <p className="mt-3 flex items-center gap-2 text-lg font-semibold text-emerald-700"><i className="fas fa-circle-check" /> Certificado válido</p>
                        <dl className="mt-3 grid grid-cols-3 gap-y-1 text-sm">
                            <dt className="text-gray-500">Servidor</dt><dd className="col-span-2">{c.servidor}</dd>
                            <dt className="text-gray-500">Curso</dt><dd className="col-span-2">{c.curso}</dd>
                            <dt className="text-gray-500">Carga horária</dt><dd className="col-span-2">{String(c.carga_horaria).replace('.', ',')} h</dd>
                            <dt className="text-gray-500">Nota</dt><dd className="col-span-2">{c.nota}</dd>
                            <dt className="text-gray-500">Concluído em</dt><dd className="col-span-2">{c.concluido_em}</dd>
                            {c.orgao && <><dt className="text-gray-500">Órgão</dt><dd className="col-span-2">{c.orgao}</dd></>}
                        </dl>
                    </>
                ) : <p className="mt-3 flex items-center gap-2 text-lg font-semibold text-red-700"><i className="fas fa-circle-xmark" /> Certificado não encontrado</p>}
            </div>
        </div>
    );
}
