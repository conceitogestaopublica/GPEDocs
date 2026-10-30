/**
 * Meus treinamentos (TR 4.24.1, 4.24.3): cursos publicados por módulo, com o progresso de cada um, a nota e o
 * certificado dos concluídos.
 */
import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Button from '../../Components/Button';

const SIT = { nao_iniciado: ['Não iniciado', 'bg-gray-100 text-gray-600'], em_andamento: ['Assistindo', 'bg-cyan-100 text-cyan-800'],
    avaliacao: ['Falta a avaliação', 'bg-amber-100 text-amber-800'], concluido: ['Concluído', 'bg-emerald-100 text-emerald-700'] };

export default function Index({ cursos, modulos, podeGerir }) {
    const [modulo, setModulo] = useState('');
    const lista = cursos.filter((c) => ! modulo || c.modulo === modulo);
    const feitos = cursos.filter((c) => c.situacao === 'concluido').length;

    return (
        <AdminLayout>
            <Head title="Meus treinamentos" />
            <PageHeader title="Meus treinamentos" subtitle={`${feitos} de ${cursos.length} curso(s) concluído(s)`}>
                {podeGerir && <Link href="/ead/gestao"><Button size="sm" variant="secondary" icon="fas fa-chalkboard">Gerir cursos</Button></Link>}
            </PageHeader>
            <div className="mb-4 flex flex-wrap gap-1.5">
                {[['', 'Todos'], ...Object.entries(modulos).filter(([k]) => cursos.some((c) => c.modulo === k))].map(([k, r]) => (
                    <button key={k} type="button" onClick={() => setModulo(k)} className={`rounded-full px-3 py-1 text-xs ${modulo === k ? 'bg-cyan-700 text-white' : 'border border-gray-300 bg-white text-gray-600'}`}>{r}</button>
                ))}
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {lista.map((c) => (
                    <div key={c.id} className="flex flex-col rounded-xl border border-[var(--ds-border)] bg-white p-4">
                        <div className="flex items-start gap-2">
                            <i className="fas fa-graduation-cap mt-1 text-xl text-indigo-600" />
                            <div className="min-w-0 flex-1"><h3 className="font-semibold text-gray-800">{c.titulo}</h3>
                                <p className="text-xs text-gray-500">{modulos[c.modulo] || 'Uso geral'} · {String(c.carga_horaria).replace('.', ',')} h · {c.aulas} aula(s)</p></div>
                            <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold ${SIT[c.situacao][1]}`}>{SIT[c.situacao][0]}</span>
                        </div>
                        {c.descricao && <p className="mt-2 line-clamp-3 text-sm text-gray-600">{c.descricao}</p>}
                        <div className="mt-auto pt-3">
                            <div className="h-2 overflow-hidden rounded-full bg-gray-200"><div className="h-full bg-emerald-600" style={{ width: `${c.percentual}%` }} /></div>
                            <p className="mt-1 text-[11px] text-gray-500">{c.aulas_concluidas} de {c.aulas} aula(s){c.nota != null ? ` · nota ${c.nota}` : ''}</p>
                            <div className="mt-2 flex gap-2">
                                <Link href={`/ead/cursos/${c.id}`} className="flex-1"><Button size="sm" className="w-full" icon={c.situacao === 'concluido' ? 'fas fa-rotate-left' : 'fas fa-play'}>
                                    {c.situacao === 'nao_iniciado' ? 'Começar' : c.situacao === 'concluido' ? 'Rever aulas' : 'Continuar'}</Button></Link>
                                {c.certificado && <a href={c.certificado} target="_blank" rel="noreferrer"><Button size="sm" variant="secondary" icon="fas fa-award">Certificado</Button></a>}
                            </div>
                        </div>
                    </div>
                ))}
                {! lista.length && <p className="text-sm text-gray-500">Nenhum curso disponível.</p>}
            </div>
        </AdminLayout>
    );
}
