/**
 * Consulta do processo sem login (TR 4.48.27): andamento por setor, decisão, pareceres (com PDF),
 * participantes e os documentos liberados ao requerente.
 */
import { Head } from '@inertiajs/react';

const STATUS = { aberto: 'Em andamento', em_tramitacao: 'Em andamento', aguardando_assinatura: 'Aguardando assinatura', concluido: 'Concluído', arquivado: 'Arquivado', cancelado: 'Cancelado' };
const DECISAO = { deferido: ['Deferido', 'bg-emerald-100 text-emerald-800'], indeferido: ['Indeferido', 'bg-red-100 text-red-700'], parcial: ['Deferido em parte', 'bg-amber-100 text-amber-800'], arquivado: ['Arquivado', 'bg-gray-100 text-gray-700'] };
const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');

export default function ConsultaProcesso({ processo: p, andamento = [], pareceres = [], participantes = [], documentos = [] }) {
    const [dec, corDec] = DECISAO[p.decisao] || [];
    return (
        <div className="min-h-screen bg-gray-50 py-8 px-4">
            <Head title={`Protocolo ${p.numero}`} />
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="rounded-2xl bg-white shadow p-6">
                    <p className="text-xs text-gray-500">{p.orgao}</p>
                    <h1 className="mt-1 text-xl font-semibold text-gray-800">Protocolo {p.numero}</h1>
                    <p className="text-sm text-gray-700">{p.assunto}</p>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs">
                        {p.tipo && <span className="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5">{p.tipo}</span>}
                        <span className="rounded-full bg-cyan-50 text-cyan-800 px-2.5 py-0.5">{STATUS[p.status] || p.status}</span>
                        {dec && <span className={`rounded-full px-2.5 py-0.5 font-semibold ${corDec}`}>{dec}</span>}
                        <span className="text-gray-500">aberto em {dt(p.aberto_em)}{p.concluido_em ? ` · concluído em ${dt(p.concluido_em)}` : ''}</span>
                    </div>
                </div>

                <div className="rounded-2xl bg-white shadow p-6">
                    <h2 className="text-sm font-semibold text-gray-700 mb-3">Andamento</h2>
                    <ol className="relative border-l-2 border-cyan-100 ml-2 space-y-3">
                        {andamento.map((a, i) => (
                            <li key={i} className="ml-4">
                                <span className="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full bg-cyan-600" />
                                <p className="text-sm text-gray-800">{a.setor}{a.sigla ? ` (${a.sigla})` : ''}</p>
                                <p className="text-xs text-gray-500">{dt(a.desde)} · {a.situacao}</p>
                            </li>
                        ))}
                    </ol>
                </div>

                {pareceres.length > 0 && (
                    <div className="rounded-2xl bg-white shadow p-6">
                        <h2 className="text-sm font-semibold text-gray-700 mb-3">Pareceres</h2>
                        <ul className="space-y-3">
                            {pareceres.map((x) => (
                                <li key={x.id} className="text-sm">
                                    <a href={x.pdf} target="_blank" rel="noreferrer" className="font-medium text-cyan-700 hover:underline">{x.numero}</a> — {x.assunto}
                                    <span className="ml-2 text-[11px] rounded-full bg-gray-100 text-gray-700 px-2 py-0.5">{x.conclusao}</span>
                                    {x.ementa && <p className="text-xs text-gray-600 italic mt-0.5">{x.ementa}</p>}
                                    <p className="text-[11px] text-gray-400">{dt(x.em)}</p>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {(participantes.length > 0 || documentos.length > 0) && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {participantes.length > 0 && (
                            <div className="rounded-2xl bg-white shadow p-6">
                                <h2 className="text-sm font-semibold text-gray-700 mb-2">Participantes</h2>
                                <ul className="text-sm space-y-1">{participantes.map((x, i) => <li key={i}><span className="text-gray-500">{x.papel}:</span> {x.nome}{x.registro ? ` · ${x.registro}` : ''}</li>)}</ul>
                            </div>
                        )}
                        {documentos.length > 0 && (
                            <div className="rounded-2xl bg-white shadow p-6">
                                <h2 className="text-sm font-semibold text-gray-700 mb-2">Documentos</h2>
                                <ul className="text-sm space-y-1">{documentos.map((d) => <li key={d.id}><a href={d.url} className="text-cyan-700 hover:underline"><i className="fas fa-file-arrow-down mr-1" />{d.nome}</a></li>)}</ul>
                            </div>
                        )}
                    </div>
                )}
                <p className="text-center text-xs text-gray-400">Guarde este link: ele dá acesso a esta consulta sem login.</p>
            </div>
        </div>
    );
}