/**
 * Na tela do documento: onde ele foi citado (#), quem foi mencionado (@) e as assinaturas
 * pedidas no texto (!) com a situação de cada uma (TR 4.16.13–4.16.18).
 */
import { Link } from '@inertiajs/react';
import Card from './Card';

const SITUACAO = { pendente: ['Aguardando', 'bg-amber-100 text-amber-800'], assinado: ['Assinado', 'bg-emerald-100 text-emerald-800'], recusado: ['Recusado', 'bg-red-100 text-red-700'] };

export default function MarcadoresDoDocumento({ dados, className = '' }) {
    const citado = dados?.citado_em || [];
    const pessoas = dados?.pessoas || [];
    if (! citado.length && ! pessoas.length) return null;
    const mencoes = pessoas.filter((p) => p.tipo === 'mencao');
    const assinaturas = pessoas.filter((p) => p.tipo === 'assinatura');

    return (
        <Card title="Citações e menções" className={className}>
            <div className="space-y-4 text-sm">
                {citado.length > 0 && (
                    <div>
                        <p className="text-xs font-semibold uppercase text-gray-500 mb-1"><i className="fas fa-hashtag mr-1" />Citado em</p>
                        <ul className="space-y-1">
                            {citado.map((c, i) => (
                                <li key={i}>
                                    <Link href={c.url} className="font-medium text-cyan-700 hover:underline">{c.rotulo}</Link>
                                    {c.assunto && <span className="text-gray-600"> — {c.assunto}</span>}
                                    <span className="block text-[11px] text-gray-400">{new Date(c.em).toLocaleString('pt-BR')}{c.por ? ` · por ${c.por}` : ''}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {mencoes.length > 0 && (
                    <div>
                        <p className="text-xs font-semibold uppercase text-gray-500 mb-1"><i className="fas fa-at mr-1" />Mencionados no texto</p>
                        <div className="flex flex-wrap gap-1.5">
                            {mencoes.map((m, i) => (
                                <span key={i} className="rounded-full bg-sky-50 text-sky-800 px-2 py-0.5 text-xs">{m.nome}{m.externo && <span className="text-sky-500"> · externo (e-mail)</span>}</span>
                            ))}
                        </div>
                    </div>
                )}
                {assinaturas.length > 0 && (
                    <div>
                        <p className="text-xs font-semibold uppercase text-gray-500 mb-1"><i className="fas fa-file-signature mr-1" />Assinaturas pedidas no texto</p>
                        <ul className="divide-y divide-gray-100">
                            {assinaturas.map((a, i) => {
                                const [rot, cor] = SITUACAO[a.status] || ['Não pedida', 'bg-gray-100 text-gray-600'];
                                return (
                                    <li key={i} className="flex items-center gap-2 py-1">
                                        <span className="flex-1 text-gray-800">{a.nome}{a.externo && <span className="text-xs text-gray-400"> · externo</span>}</span>
                                        <span className={`text-[11px] rounded-full px-2 py-0.5 ${cor}`}>{rot}</span>
                                    </li>
                                );
                            })}
                        </ul>
                        {assinaturas[0]?.documento_id && (
                            <Link href={`/documentos/${assinaturas[0].documento_id}`} className="mt-1 inline-block text-xs text-cyan-700 hover:underline">Abrir o PDF no GED</Link>
                        )}
                    </div>
                )}
            </div>
        </Card>
    );
}
