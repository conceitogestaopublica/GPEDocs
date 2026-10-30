/**
 * Configurar suporte (TR 4.26): assuntos e artigos da central de ajuda (autoatendimento), atendentes com
 * nível, e-mail de entrada, setor da TI e assunto do chamado técnico (nível 3), prazo para fechar resolvidos.
 */
import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import RichEditor from '../../../Components/RichEditor';

const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'nunca');

export default function Index({ assuntos, artigos, atendentes, config, tiposChamado, setores, niveis, indicadores }) {
    const errors = usePage().props.errors || {};
    const [assunto, setAssunto] = useState(null);
    const [artigo, setArtigo] = useState(null);
    const [cfg, setCfg] = useState({ email_entrada: config.email_entrada || '', chamado_tipo_id: config.chamado_tipo_id || '', chamado_unidade_id: config.chamado_unidade_id || '', horas_fechamento: config.horas_fechamento || 72 });
    const [busca, setBusca] = useState('');
    const [achados, setAchados] = useState([]);
    const [nivel, setNivel] = useState(1);
    const post = (url, dados, fechar) => router.post(url, dados, { preserveScroll: true, onSuccess: () => fechar?.(null) });

    useEffect(() => {
        if (busca.trim().length < 2) { setAchados([]); return undefined; }
        const t = setTimeout(() => fetch(`/suporte/api/pessoas?q=${encodeURIComponent(busca)}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then(setAchados).catch(() => {}), 300);
        return () => clearTimeout(t);
    }, [busca]);

    return (
        <AdminLayout>
            <Head title="Configurar suporte" />
            <PageHeader title="Configurar suporte" subtitle="Central de ajuda, atendentes e encaminhamento para a TI" />
            <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <Card><p className="text-xs text-gray-500">Atendimentos em aberto</p><p className="text-2xl font-semibold">{indicadores.abertos}</p></Card>
                <Card><p className="text-xs text-gray-500">Tempo médio até a 1ª resposta</p><p className="text-2xl font-semibold">{indicadores.min_primeira_resposta != null ? `${indicadores.min_primeira_resposta} min` : '—'}</p></Card>
                <Card><p className="text-xs text-gray-500">Nota média</p><p className="text-2xl font-semibold">{indicadores.nota_media ? Number(indicadores.nota_media).toFixed(1) : '—'}</p></Card>
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card title={`Assuntos e artigos (${artigos.length})`}>
                    {assuntos.map((s) => (
                        <div key={s.id} className={`mb-3 ${s.ativo ? '' : 'opacity-50'}`}>
                            <div className="flex items-center gap-2"><b className="text-sm">{s.nome}</b>
                                <button type="button" onClick={() => setAssunto({ ...s })} className="text-xs text-cyan-700 hover:underline">editar</button>
                                <button type="button" onClick={() => setArtigo({ assunto_id: s.id, titulo: '', resumo: '', conteudo: '', publicado: true })} className="ml-auto text-xs text-cyan-700 hover:underline">+ artigo</button></div>
                            <ul className="ml-3 mt-1 space-y-0.5 text-sm">
                                {artigos.filter((a) => a.assunto_id === s.id).map((a) => (
                                    <li key={a.id} className={`flex items-center gap-2 ${a.publicado ? '' : 'opacity-50'}`}>
                                        <button type="button" onClick={() => setArtigo({ ...a })} className="text-left text-gray-800 hover:underline">{a.titulo}</button>
                                        <span className="text-[11px] text-gray-400">{a.visualizacoes} vis. · resolveu {a.resolveu} · não {a.nao_resolveu}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                    <Button size="sm" icon="fas fa-plus" onClick={() => setAssunto({ nome: '', descricao: '', ordem: 0, ativo: true })}>Assunto</Button>
                </Card>

                <div className="space-y-4">
                    <Card title={`Atendentes (${atendentes.length})`}>
                        <ul className="divide-y divide-gray-100 text-sm">
                            {atendentes.map((a) => (
                                <li key={a.id} className="flex items-center gap-2 py-1.5">
                                    <span className={`h-2 w-2 rounded-full ${a.disponivel && a.visto_em && Date.now() - new Date(a.visto_em) < 120000 ? 'bg-emerald-500' : 'bg-gray-300'}`} />
                                    <span className="flex-1">{a.name}<span className="block text-[11px] text-gray-500">visto {dt(a.visto_em)}{a.disponivel ? '' : ' · indisponível'}</span></span>
                                    <select value={a.nivel} onChange={(e) => post('/suporte/atendentes', { user_id: a.id, nivel: e.target.value })} className="ds-input !w-auto text-xs">
                                        {Object.entries(niveis).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select>
                                    <button type="button" onClick={() => post('/suporte/atendentes', { user_id: a.id, nivel: a.nivel, remover: true })} className="text-xs text-red-600">remover</button>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-3 flex gap-2">
                            <div className="relative flex-1">
                                <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Adicionar atendente: nome ou e-mail" className="ds-input" />
                                {achados.length > 0 && (
                                    <ul className="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
                                        {achados.map((p) => <li key={p.id}><button type="button" onClick={() => { post('/suporte/atendentes', { user_id: p.id, nivel }); setBusca(''); }} className="w-full px-3 py-1.5 text-left text-sm hover:bg-gray-50">{p.name}<span className="block text-xs text-gray-500">{p.email}</span></button></li>)}
                                    </ul>
                                )}
                            </div>
                            <select value={nivel} onChange={(e) => setNivel(e.target.value)} className="ds-input !w-auto">{Object.keys(niveis).map((k) => <option key={k} value={k}>Nível {k}</option>)}</select>
                        </div>
                        <p className="mt-2 text-[11px] text-gray-500">O atendente também precisa da permissão "suporte.atender" no perfil. Fica on-line enquanto está com o sistema aberto e marcado como disponível na Central de suporte.</p>
                    </Card>

                    <Card title="Encaminhamento e e-mail">
                        <div className="space-y-3">
                            <label className="block text-xs text-gray-600">E-mail de entrada do suporte<input value={cfg.email_entrada} onChange={(e) => setCfg({ ...cfg, email_entrada: e.target.value })} className="ds-input mt-1" placeholder="suporte@prefeitura.gov.br" />
                                <span className="text-[11px] text-gray-500">E-mail de servidor que chega neste endereço abre atendimento.</span></label>
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <label className="text-xs text-gray-600">Setor da TI (nível 3)<select value={cfg.chamado_unidade_id} onChange={(e) => setCfg({ ...cfg, chamado_unidade_id: e.target.value })} className="ds-input mt-1">
                                    <option value="">—</option>{setores.map((s) => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}</select></label>
                                <label className="text-xs text-gray-600">Assunto do chamado técnico<select value={cfg.chamado_tipo_id} onChange={(e) => setCfg({ ...cfg, chamado_tipo_id: e.target.value })} className="ds-input mt-1">
                                    <option value="">O do módulo Chamado técnico</option>{tiposChamado.map((t) => <option key={t.id} value={t.id}>{t.nome}</option>)}</select></label>
                            </div>
                            <label className="block text-xs text-gray-600">Fechar resolvidos sem resposta após (horas)<input type="number" min="1" max="720" value={cfg.horas_fechamento} onChange={(e) => setCfg({ ...cfg, horas_fechamento: e.target.value })} className="ds-input mt-1 !w-32" /></label>
                            {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                            <Button size="sm" icon="fas fa-save" onClick={() => post('/suporte/configuracao', cfg)}>Salvar</Button>
                        </div>
                    </Card>
                </div>
            </div>

            <Modal show={!! assunto} onClose={() => setAssunto(null)} title={assunto?.id ? 'Editar assunto' : 'Novo assunto'}>
                {assunto && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Nome<input value={assunto.nome} onChange={(e) => setAssunto({ ...assunto, nome: e.target.value })} className="ds-input mt-1" /></label>
                        <label className="block text-sm text-gray-700">Descrição<input value={assunto.descricao || ''} onChange={(e) => setAssunto({ ...assunto, descricao: e.target.value })} className="ds-input mt-1" /></label>
                        <div className="flex items-center gap-4">
                            <label className="text-sm text-gray-700">Ordem<input type="number" value={assunto.ordem} onChange={(e) => setAssunto({ ...assunto, ordem: e.target.value })} className="ds-input mt-1 !w-24" /></label>
                            <label className="mt-5 flex items-center gap-2 text-sm"><input type="checkbox" checked={assunto.ativo} onChange={(e) => setAssunto({ ...assunto, ativo: e.target.checked })} /> Ativo</label>
                        </div>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setAssunto(null)}>Cancelar</Button>
                            <Button onClick={() => post(assunto.id ? `/suporte/assuntos/${assunto.id}` : '/suporte/assuntos', assunto, setAssunto)}>Salvar</Button></div>
                    </div>
                )}
            </Modal>

            <Modal show={!! artigo} onClose={() => setArtigo(null)} title={artigo?.id ? 'Editar artigo' : 'Novo artigo'} maxWidth="4xl">
                {artigo && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <label className="text-sm text-gray-700 md:col-span-2">Título<input value={artigo.titulo} onChange={(e) => setArtigo({ ...artigo, titulo: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="text-sm text-gray-700">Assunto<select value={artigo.assunto_id} onChange={(e) => setArtigo({ ...artigo, assunto_id: e.target.value })} className="ds-input mt-1">
                                {assuntos.map((s) => <option key={s.id} value={s.id}>{s.nome}</option>)}</select></label>
                        </div>
                        <label className="block text-sm text-gray-700">Resumo<input value={artigo.resumo || ''} onChange={(e) => setArtigo({ ...artigo, resumo: e.target.value })} className="ds-input mt-1" /></label>
                        <RichEditor html={artigo.conteudo} onChange={(v) => setArtigo((x) => ({ ...x, conteudo: v }))} minHeight={260} placeholder="Passo a passo…" />
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={artigo.publicado} onChange={(e) => setArtigo({ ...artigo, publicado: e.target.checked })} /> Publicado</label>
                        {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                        <div className="flex justify-end gap-2">
                            {artigo.id && <Button variant="danger" className="mr-auto" onClick={() => window.confirm('Excluir o artigo?') && router.delete(`/suporte/artigos/${artigo.id}`, { preserveScroll: true, onSuccess: () => setArtigo(null) })}>Excluir</Button>}
                            <Button variant="secondary" onClick={() => setArtigo(null)}>Cancelar</Button>
                            <Button onClick={() => post(artigo.id ? `/suporte/artigos/${artigo.id}` : '/suporte/artigos', artigo, setArtigo)}>Salvar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
