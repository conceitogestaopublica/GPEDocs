/** Gestão dos cursos do EAD: lista com aulas, questões, matriculados e concluídos; criar curso. */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import Modal from '../../Components/Modal';

export default function Gestao({ cursos, modulos }) {
    const [novo, setNovo] = useState(null);
    return (
        <AdminLayout>
            <Head title="Cursos do EAD" />
            <PageHeader title="Cursos do EAD" subtitle="Aulas em vídeo, avaliação e certificado">
                <Link href="/ead/relatorio"><Button size="sm" variant="secondary" icon="fas fa-user-graduate">Progresso dos servidores</Button></Link>
                <Button size="sm" icon="fas fa-plus" onClick={() => setNovo({ titulo: '', modulo: 'geral', carga_horaria: 1, percentual_minimo: 90, nota_minima: 70, ordem_obrigatoria: true })}>Curso</Button>
            </PageHeader>
            <Card>
                <table className="w-full text-sm">
                    <thead><tr className="border-b text-left text-xs text-gray-500"><th className="py-2">Curso</th><th>Módulo</th><th>Aulas</th><th>Questões</th><th>Matriculados</th><th>Concluídos</th><th>Situação</th></tr></thead>
                    <tbody>
                        {cursos.map((c) => (
                            <tr key={c.id} className="border-b border-gray-100">
                                <td className="py-2"><Link href={`/ead/gestao/${c.id}`} className="font-medium text-cyan-700 hover:underline">{c.titulo}</Link></td>
                                <td className="text-xs">{modulos[c.modulo] || '—'}</td><td>{c.aulas}</td><td>{c.questoes}</td><td>{c.matriculados}</td><td>{c.concluidos}</td>
                                <td><span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${c.publicado ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{c.publicado ? 'Publicado' : 'Rascunho'}</span></td>
                            </tr>
                        ))}
                        {! cursos.length && <tr><td colSpan={7} className="py-4 text-center text-gray-400">Nenhum curso. Crie o primeiro.</td></tr>}
                    </tbody>
                </table>
            </Card>
            <Modal show={!! novo} onClose={() => setNovo(null)} title="Novo curso">
                {novo && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Título<input value={novo.titulo} onChange={(e) => setNovo({ ...novo, titulo: e.target.value })} className="ds-input mt-1" /></label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-sm text-gray-700">Módulo<select value={novo.modulo} onChange={(e) => setNovo({ ...novo, modulo: e.target.value })} className="ds-input mt-1">
                                {Object.entries(modulos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <label className="text-sm text-gray-700">Carga horária (h)<input type="number" step="0.5" min="0.5" value={novo.carga_horaria} onChange={(e) => setNovo({ ...novo, carga_horaria: e.target.value })} className="ds-input mt-1" /></label>
                        </div>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setNovo(null)}>Cancelar</Button>
                            <Button onClick={() => router.post('/ead/gestao', novo)}>Criar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
