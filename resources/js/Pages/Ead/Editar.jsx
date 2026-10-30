/**
 * Editar curso do EAD: dados (carga horária, ordem obrigatória, % mínimo da aula, nota mínima, tentativas,
 * publicação), aulas (vídeo enviado ou link do YouTube) e questões objetivas da avaliação final.
 */
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import Modal from '../../Components/Modal';

const mmss = (s) => (s == null ? 'duração lida na 1ª exibição' : `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`);

export default function Editar({ curso, aulas, questoes, modulos, videoMaxMb = 50 }) {
    const errors = usePage().props.errors || {};
    const [c, setC] = useState({ ...curso, descricao: curso.descricao || '', tentativas: curso.tentativas || '' });
    const [aula, setAula] = useState(null);
    const [questao, setQuestao] = useState(null);
    const campo = (k) => ({ value: c[k] ?? '', onChange: (e) => setC({ ...c, [k]: e.target.value }) });
    const salvarAula = () => router.post(aula.id ? `/ead/gestao/${curso.id}/aulas/${aula.id}` : `/ead/gestao/${curso.id}/aulas`,
        { ...aula, video: aula.video || null }, { forceFormData: true, preserveScroll: true, onSuccess: () => setAula(null) });

    return (
        <AdminLayout>
            <Head title={`Curso: ${curso.titulo}`} />
            <PageHeader title={curso.titulo} subtitle={curso.publicado ? 'Publicado — os servidores já veem este curso' : 'Rascunho — publique quando as aulas estiverem prontas'}>
                <Link href={`/ead/cursos/${curso.id}`}><Button size="sm" variant="secondary" icon="fas fa-eye">Ver como servidor</Button></Link>
                <Link href="/ead/gestao"><Button size="sm" variant="secondary" icon="fas fa-arrow-left">Cursos</Button></Link>
            </PageHeader>
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card title="Dados do curso">
                    <div className="space-y-3">
                        <label className="block text-xs text-gray-600">Título<input {...campo('titulo')} className="ds-input mt-1" /></label>
                        <label className="block text-xs text-gray-600">Descrição<textarea {...campo('descricao')} rows={3} className="ds-input !h-auto mt-1" /></label>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="text-xs text-gray-600">Módulo<select {...campo('modulo')} className="ds-input mt-1">{Object.entries(modulos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <label className="text-xs text-gray-600">Carga horária (h)<input type="number" step="0.5" {...campo('carga_horaria')} className="ds-input mt-1" /></label>
                            <label className="text-xs text-gray-600">% da aula para concluir<input type="number" {...campo('percentual_minimo')} className="ds-input mt-1" /></label>
                            <label className="text-xs text-gray-600">Nota mínima<input type="number" {...campo('nota_minima')} className="ds-input mt-1" /></label>
                            <label className="text-xs text-gray-600">Tentativas (vazio = livre)<input type="number" {...campo('tentativas')} className="ds-input mt-1" /></label>
                        </div>
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!! c.ordem_obrigatoria} onChange={(e) => setC({ ...c, ordem_obrigatoria: e.target.checked })} /> Ordem obrigatória das aulas</label>
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!! c.publicado} onChange={(e) => setC({ ...c, publicado: e.target.checked })} /> Publicado</label>
                        {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                        <Button size="sm" icon="fas fa-save" onClick={() => router.post(`/ead/gestao/${curso.id}`, { ...c, tentativas: c.tentativas || null }, { preserveScroll: true })}>Salvar</Button>
                    </div>
                </Card>

                <Card title={`Aulas (${aulas.length})`}>
                    <ol className="divide-y divide-gray-100 text-sm">
                        {aulas.map((a) => (
                            <li key={a.id} className="flex items-center gap-2 py-2">
                                <i className={a.video_tipo === 'youtube' ? 'fa-brands fa-youtube text-red-600' : 'fas fa-film text-cyan-700'} />
                                <span className="flex-1">{a.ordem}. {a.titulo}<span className="block text-[11px] text-gray-500">{mmss(a.duracao_seg)}</span></span>
                                <button type="button" onClick={() => setAula({ ...a, descricao: a.descricao || '', video_url: a.video_url || '', video: null })} className="text-xs text-cyan-700">editar</button>
                                <button type="button" onClick={() => window.confirm('Excluir a aula?') && router.delete(`/ead/gestao/${curso.id}/aulas/${a.id}`, { preserveScroll: true })} className="text-xs text-red-600">excluir</button>
                            </li>
                        ))}
                    </ol>
                    <Button size="sm" className="mt-2" icon="fas fa-plus" onClick={() => setAula({ titulo: '', descricao: '', ordem: aulas.length + 1, video_tipo: 'arquivo', video_url: '', video: null })}>Aula</Button>
                </Card>

                <Card title={`Avaliação final (${questoes.length} questões)`}>
                    <ol className="divide-y divide-gray-100 text-sm">
                        {questoes.map((q) => (
                            <li key={q.id} className="flex items-start gap-2 py-2">
                                <span className="flex-1">{q.ordem}. {q.enunciado}<span className="block text-[11px] text-emerald-700">Certa: {q.alternativas[q.correta]}{q.peso > 1 ? ` · peso ${q.peso}` : ''}</span></span>
                                <button type="button" onClick={() => setQuestao({ ...q })} className="text-xs text-cyan-700">editar</button>
                                <button type="button" onClick={() => window.confirm('Excluir a questão?') && router.delete(`/ead/gestao/${curso.id}/questoes/${q.id}`, { preserveScroll: true })} className="text-xs text-red-600">excluir</button>
                            </li>
                        ))}
                    </ol>
                    <Button size="sm" className="mt-2" icon="fas fa-plus" onClick={() => setQuestao({ enunciado: '', alternativas: ['', '', '', ''], correta: 0, peso: 1, ordem: questoes.length + 1 })}>Questão</Button>
                    <p className="mt-2 text-[11px] text-gray-500">Sem questões, o curso é concluído ao assistir todas as aulas.</p>
                </Card>
            </div>

            <Modal show={!! aula} onClose={() => setAula(null)} title={aula?.id ? 'Editar aula' : 'Nova aula'}>
                {aula && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-4 gap-2">
                            <label className="col-span-3 text-sm text-gray-700">Título<input value={aula.titulo} onChange={(e) => setAula({ ...aula, titulo: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="text-sm text-gray-700">Ordem<input type="number" min="1" value={aula.ordem} onChange={(e) => setAula({ ...aula, ordem: e.target.value })} className="ds-input mt-1" /></label>
                        </div>
                        <label className="block text-sm text-gray-700">Descrição<textarea value={aula.descricao} onChange={(e) => setAula({ ...aula, descricao: e.target.value })} rows={2} className="ds-input !h-auto mt-1" /></label>
                        <div className="flex gap-4 text-sm">
                            <label className="flex items-center gap-1.5"><input type="radio" checked={aula.video_tipo === 'arquivo'} onChange={() => setAula({ ...aula, video_tipo: 'arquivo' })} /> Enviar vídeo (MP4)</label>
                            <label className="flex items-center gap-1.5"><input type="radio" checked={aula.video_tipo === 'youtube'} onChange={() => setAula({ ...aula, video_tipo: 'youtube' })} /> Link do YouTube</label>
                        </div>
                        {aula.video_tipo === 'arquivo'
                            ? <input type="file" accept="video/mp4,video/webm" onChange={(e) => setAula({ ...aula, video: e.target.files[0] })} className="block w-full text-sm" />
                            : <input value={aula.video_url} onChange={(e) => setAula({ ...aula, video_url: e.target.value })} placeholder="https://www.youtube.com/watch?v=…" className="ds-input" />}
                        {aula.video_tipo === 'arquivo' && <p className="text-[11px] text-gray-500">Até {videoMaxMb} MB.{aula.id ? ' Deixe vazio para manter o vídeo atual.' : ''} Vídeo maior: publique no YouTube como "não listado" e use o link.</p>}
                        {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setAula(null)}>Cancelar</Button><Button onClick={salvarAula}>Salvar</Button></div>
                    </div>
                )}
            </Modal>

            <Modal show={!! questao} onClose={() => setQuestao(null)} title={questao?.id ? 'Editar questão' : 'Nova questão'}>
                {questao && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Enunciado<textarea value={questao.enunciado} onChange={(e) => setQuestao({ ...questao, enunciado: e.target.value })} rows={2} className="ds-input !h-auto mt-1" /></label>
                        <p className="text-xs text-gray-600">Alternativas — marque a certa:</p>
                        {questao.alternativas.map((alt, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <input type="radio" checked={questao.correta === i} onChange={() => setQuestao({ ...questao, correta: i })} aria-label={`Alternativa ${i + 1} é a certa`} />
                                <input value={alt} onChange={(e) => setQuestao({ ...questao, alternativas: questao.alternativas.map((x, j) => (j === i ? e.target.value : x)) })} className="ds-input" placeholder={`Alternativa ${i + 1}`} />
                                {questao.alternativas.length > 2 && <button type="button" onClick={() => setQuestao({ ...questao, alternativas: questao.alternativas.filter((_, j) => j !== i), correta: questao.correta >= i && questao.correta > 0 ? questao.correta - 1 : questao.correta })} className="text-xs text-red-600">×</button>}
                            </div>
                        ))}
                        {questao.alternativas.length < 6 && <button type="button" onClick={() => setQuestao({ ...questao, alternativas: [...questao.alternativas, ''] })} className="text-xs text-cyan-700">+ alternativa</button>}
                        <div className="grid grid-cols-2 gap-2">
                            <label className="text-sm text-gray-700">Peso<input type="number" min="1" max="10" value={questao.peso} onChange={(e) => setQuestao({ ...questao, peso: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="text-sm text-gray-700">Ordem<input type="number" min="1" value={questao.ordem} onChange={(e) => setQuestao({ ...questao, ordem: e.target.value })} className="ds-input mt-1" /></label>
                        </div>
                        {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setQuestao(null)}>Cancelar</Button>
                            <Button onClick={() => router.post(questao.id ? `/ead/gestao/${curso.id}/questoes/${questao.id}` : `/ead/gestao/${curso.id}/questoes`, questao, { preserveScroll: true, onSuccess: () => setQuestao(null) })}>Salvar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
