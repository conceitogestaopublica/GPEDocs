/**
 * Busca avançada (TR 4.15): processos, memorandos e ofícios, pelo conteúdo (inclusive o texto
 * dos anexos, com OCR), frase exata entre aspas, tipo + número + ano, período, assunto(s),
 * setor e escopo, usuário interno, interessado externo, etiqueta e situação geral.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';

const TIPOS = [['processo', 'Processos'], ['memorando', 'Memorandos'], ['oficio', 'Ofícios']];
const SITUACOES = [['', 'Qualquer'], ['aberto', 'Em aberto'], ['parcial', 'Parcialmente arquivado'], ['arquivado', 'Arquivado']];
const ROTULO = { processo: 'Processo', memorando: 'Memorando', oficio: 'Ofício' };

export default function Busca({ filtros, buscou, resultados = [], limitado = false, opcoes = {} }) {
    const [f, setF] = useState({ ...filtros, ano: filtros.ano || '', setor: filtros.setor || '', interno: filtros.interno || '', etiqueta: filtros.etiqueta || '', situacao: filtros.situacao || '', de: filtros.de || '', ate: filtros.ate || '' });
    const set = (k, v) => setF(x => ({ ...x, [k]: v }));
    const buscar = (e) => {
        e?.preventDefault();
        const p = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null && ! (Array.isArray(v) && v.length === 0)));
        router.get('/flow/busca', p, { preserveState: true });
    };
    const alternar = (k, v) => set(k, f[k].includes(v) ? f[k].filter(x => x !== v) : [...f[k], v]);

    return (
        <AdminLayout>
            <Head title="Busca avançada" />
            <PageHeader title="Busca avançada" subtitle="Processos, memorandos e ofícios — inclusive o texto dos anexos (PDF escaneado e imagem lidos por OCR)" />

            <Card className="mb-4">
                <form onSubmit={buscar} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Termos</label>
                        <input value={f.q} onChange={(e) => set('q', e.target.value)} className="ds-input" placeholder='Ex.: alvará "Rua das Acácias"' />
                        <p className="mt-1 text-[11px] text-gray-500">Todas as palavras precisam aparecer. Use aspas para a frase exata. Procura no número, assunto, descrição, despachos, pareceres, comentários e no texto dos anexos.</p>
                    </div>
                    <div className="flex flex-wrap gap-4">
                        {TIPOS.map(([v, r]) => (
                            <label key={v} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" checked={f.tipos.includes(v)} onChange={() => alternar('tipos', v)} className="rounded border-gray-300 text-blue-600" />{r}
                            </label>
                        ))}
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div><label className="block text-xs font-medium text-gray-600 mb-1">Número</label><input value={f.numero} onChange={(e) => set('numero', e.target.value)} className="ds-input" placeholder="000123" /></div>
                        <div><label className="block text-xs font-medium text-gray-600 mb-1">Ano</label><input type="number" value={f.ano} onChange={(e) => set('ano', e.target.value)} className="ds-input" placeholder="2026" /></div>
                        <div><label className="block text-xs font-medium text-gray-600 mb-1">De</label><input type="date" value={f.de} onChange={(e) => set('de', e.target.value)} className="ds-input" /></div>
                        <div><label className="block text-xs font-medium text-gray-600 mb-1">Até</label><input type="date" value={f.ate} onChange={(e) => set('ate', e.target.value)} className="ds-input" /></div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Onde procurar</label>
                            <select value={f.escopo} onChange={(e) => set('escopo', e.target.value)} className="ds-input">
                                <option value="meus">Todos os meus setores</option>
                                <option value="atual">Só o meu setor principal</option>
                                {opcoes.pode_todos && <option value="todos">Toda a UG</option>}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Setor</label>
                            <select value={f.setor} onChange={(e) => set('setor', e.target.value)} className="ds-input">
                                <option value="">—</option>
                                {(opcoes.setores || []).map(s => <option key={s.id} value={s.id}>{s.nome}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Situação geral</label>
                            <select value={f.situacao} onChange={(e) => set('situacao', e.target.value)} className="ds-input">
                                {SITUACOES.map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Usuário interno (quem abriu ou despachou)</label>
                            <select value={f.interno} onChange={(e) => set('interno', e.target.value)} className="ds-input">
                                <option value="">—</option>
                                {(opcoes.usuarios || []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Interessado externo (nome ou CPF/CNPJ)</label>
                            <input value={f.externo} onChange={(e) => set('externo', e.target.value)} className="ds-input" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Etiqueta</label>
                            <select value={f.etiqueta} onChange={(e) => set('etiqueta', e.target.value)} className="ds-input">
                                <option value="">—</option>
                                {(opcoes.etiquetas || []).map(e => <option key={e.id} value={e.id}>{e.nome} ({e.setor})</option>)}
                            </select>
                        </div>
                    </div>
                    {f.tipos.includes('processo') && (opcoes.assuntos || []).length > 0 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Assuntos (processos)</label>
                            <div className="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
                                {opcoes.assuntos.map(a => (
                                    <button key={a.id} type="button" onClick={() => alternar('assuntos', a.id)}
                                        className={`text-xs rounded-full px-2.5 py-1 border ${f.assuntos.includes(a.id) ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-600 border-gray-200'}`}>{a.nome}</button>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="flex gap-2">
                        <Button type="submit" variant="primary" icon="fas fa-magnifying-glass">Buscar</Button>
                        <Button type="button" variant="secondary" onClick={() => router.get('/flow/busca')}>Limpar</Button>
                    </div>
                </form>
            </Card>

            {buscou && (
                <Card title={`${resultados.length}${limitado ? '+' : ''} resultado(s)`}>
                    {resultados.length === 0 ? <p className="text-sm text-gray-400">Nada encontrado com esses filtros.</p> : (
                        <ul className="divide-y divide-gray-100">
                            {resultados.map(r => (
                                <li key={`${r.tipo}-${r.id}`} className="py-2.5">
                                    <Link href={r.link} className="group block">
                                        <p className="text-sm">
                                            <span className="text-[10px] font-bold uppercase text-gray-500 mr-2">{ROTULO[r.tipo]}</span>
                                            <span className="font-mono text-xs text-gray-500 mr-2">{r.numero}</span>
                                            <span className="text-gray-800 group-hover:text-blue-700 font-medium">{r.titulo}</span>
                                        </p>
                                        <p className="text-xs text-gray-500">{r.detalhe} · {r.situacao} · {r.data ? new Date(r.data).toLocaleDateString('pt-BR') : ''}</p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    {limitado && <p className="mt-2 text-xs text-gray-500">Mostrando os 100 mais recentes. Refine os filtros.</p>}
                </Card>
            )}
        </AdminLayout>
    );
}
