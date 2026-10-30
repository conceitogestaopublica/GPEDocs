/** Progresso de cada servidor no curso (TR 4.24.8, 4.25.2): situação, aulas, nota, datas, certificado; CSV. */
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

const COR = { nao_iniciado: 'bg-gray-100 text-gray-600', em_andamento: 'bg-cyan-100 text-cyan-800', avaliacao: 'bg-amber-100 text-amber-800', concluido: 'bg-emerald-100 text-emerald-700' };
const num = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 }));

export default function Relatorio({ cursos, cursoId, setores, unidadeId, linhas, resumo }) {
    const ir = (m) => router.get('/ead/relatorio', { curso: cursoId, unidade_id: unidadeId || undefined, ...m }, { preserveScroll: true });
    return (
        <AdminLayout>
            <Head title="Progresso dos servidores" />
            <PageHeader title="Progresso dos servidores" subtitle="Situação de cada servidor no curso">
                {cursoId && <a href={`/ead/relatorio?curso=${cursoId}${unidadeId ? `&unidade_id=${unidadeId}` : ''}&formato=csv`}><Button size="sm" variant="secondary" icon="fas fa-file-csv">CSV</Button></a>}
            </PageHeader>
            <div className="mb-3 flex flex-wrap gap-2">
                <select value={cursoId || ''} onChange={(e) => ir({ curso: e.target.value })} className="ds-input !w-auto">{cursos.map((c) => <option key={c.id} value={c.id}>{c.titulo}{c.publicado ? '' : ' (rascunho)'}</option>)}</select>
                <select value={unidadeId || ''} onChange={(e) => ir({ unidade_id: e.target.value || undefined })} className="ds-input !w-auto max-w-xs"><option value="">Todos os setores</option>
                    {setores.map((s) => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}</select>
            </div>
            <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                {[['Iniciaram', resumo.matriculados], ['Em andamento', resumo.em_andamento], ['Concluíram', resumo.concluidos], ['Nota média', num(resumo.media_nota)], ['% médio das aulas', `${num(resumo.media_percentual)}%`]].map(([r, v]) => (
                    <Card key={r}><p className="text-xs text-gray-500">{r}</p><p className="text-2xl font-semibold text-gray-800">{v}</p></Card>
                ))}
            </div>
            <Card>
                <table className="w-full text-sm">
                    <thead><tr className="border-b text-left text-xs text-gray-500"><th className="py-2">Servidor</th><th>Setor</th><th>Situação</th><th>Aulas</th><th>Nota</th><th>Tentativas</th><th>Início</th><th>Conclusão</th><th /></tr></thead>
                    <tbody>
                        {linhas.map((l) => (
                            <tr key={l.email} className="border-b border-gray-100">
                                <td className="py-2">{l.servidor}<span className="block text-[11px] text-gray-500">{l.email}</span></td>
                                <td className="text-xs">{l.setor || '—'}</td>
                                <td><span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${COR[l.situacao]}`}>{l.situacao_rotulo}</span></td>
                                <td className="text-xs">{l.aulas_concluidas}/{l.aulas} ({l.percentual}%)</td>
                                <td>{num(l.nota)}</td><td>{l.tentativas}</td><td className="text-xs">{l.iniciado}</td><td className="text-xs">{l.concluido || '—'}</td>
                                <td>{l.certificado && <a href={l.certificado} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline">certificado</a>}</td>
                            </tr>
                        ))}
                        {! linhas.length && <tr><td colSpan={9} className="py-4 text-center text-gray-400">Ninguém iniciou este curso ainda.</td></tr>}
                    </tbody>
                </table>
            </Card>
        </AdminLayout>
    );
}
