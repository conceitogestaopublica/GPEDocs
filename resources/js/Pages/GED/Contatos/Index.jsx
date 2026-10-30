/**
 * Contatos (TR 4.21): base única de pessoas físicas, jurídicas e setores de jurídica.
 * Busca, listas, unificação de duplicados e CSV (importação conferida antes de gravar).
 */
import { useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import { useConfirm } from '../../../Components/ConfirmProvider';

const ORIGEM = { interno: 'Cadastro interno', portal: 'Portal', processo: 'Requerente de processo', importacao: 'Importação' };

export default function Contatos({ contatos, filtros, tipos = {}, listas = [], duplicados = [], conferencia = null }) {
    const confirmar = useConfirm();
    const { errors = {} } = usePage().props;
    const [f, setF] = useState({ q: filtros.q || '', tipo: filtros.tipo || '', lista: filtros.lista || '' });
    const [marcados, setMarcados] = useState([]);
    const [unificar, setUnificar] = useState(null);   // { contatos: [...], principal }
    const [listaAlvo, setListaAlvo] = useState('');
    const [editandoLista, setEditandoLista] = useState(null);
    const listaForm = useForm({ nome: '', descricao: '' });
    const arquivo = useRef(null);

    const ir = (extra = {}) => {
        const p = Object.fromEntries(Object.entries({ ...f, aba: filtros.aba, ...extra }).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== 'todos'));
        router.get('/flow/contatos', p, { preserveState: true, preserveScroll: true });
    };
    const alternar = (id) => setMarcados(m => m.includes(id) ? m.filter(x => x !== id) : [...m, id]);
    const linhasPagina = contatos.data || [];
    const selecionados = linhasPagina.filter(c => marcados.includes(c.id));

    const salvarLista = (e) => {
        e.preventDefault();
        const op = { preserveScroll: true, onSuccess: () => { setEditandoLista(null); listaForm.reset(); } };
        editandoLista?.id ? listaForm.put(`/flow/contatos/listas/${editandoLista.id}`, op) : listaForm.post('/flow/contatos/listas', op);
    };
    const itens = (acao, lista) => router.post(`/flow/contatos/listas/${lista}/itens`, { contatos: marcados, acao }, { preserveScroll: true, onSuccess: () => setMarcados([]) });
    const conferir = (e) => {
        const a = e.target.files?.[0];
        if (a) router.post('/flow/contatos/conferir', { arquivo: a }, { forceFormData: true, preserveScroll: true });
        e.target.value = '';
    };
    const exportar = `/flow/contatos/exportar?${new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString()}`;

    return (
        <AdminLayout>
            <Head title="Contatos" />
            <PageHeader title="Contatos" subtitle="Pessoas físicas, jurídicas e setores de jurídica — inclui quem se cadastrou no portal e os requerentes dos processos.">
                <div className="flex flex-wrap gap-2">
                    <Button variant="primary" icon="fas fa-plus" href="/flow/contatos/novo">Novo contato</Button>
                    <Button variant="secondary" icon="fas fa-file-export" href={exportar}>Exportar CSV</Button>
                    <Button variant="secondary" icon="fas fa-file-import" onClick={() => arquivo.current?.click()}>Importar CSV</Button>
                    <input ref={arquivo} type="file" accept=".csv,text/csv" className="hidden" onChange={conferir} />
                </div>
            </PageHeader>

            {conferencia && (
                <Card title="Conferência da importação" className="mb-6">
                    <p className="text-sm text-gray-700 mb-3"><b>{conferencia.validos}</b> contato(s) prontos para entrar{conferencia.erros > 0 && <>, <b className="text-red-700">{conferencia.erros} com erro</b> (ficam de fora)</>}. Nada foi gravado ainda.</p>
                    <div className="max-h-80 overflow-y-auto border border-gray-100 rounded">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-xs text-gray-500 sticky top-0"><tr><th className="px-3 py-2 text-left">Linha</th><th className="px-3 py-2 text-left">Nome</th><th className="px-3 py-2 text-left">Tipo</th><th className="px-3 py-2 text-left">Documento</th><th className="px-3 py-2 text-left">E-mail</th><th className="px-3 py-2 text-left">Situação</th></tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {conferencia.itens.map(i => (
                                    <tr key={i.linha} className={i.erro ? 'bg-red-50' : ''}>
                                        <td className="px-3 py-1.5 text-gray-400">{i.linha}</td><td className="px-3 py-1.5">{i.nome}</td><td className="px-3 py-1.5">{tipos[i.tipo] || i.tipo}</td>
                                        <td className="px-3 py-1.5 font-mono text-xs">{i.documento || '—'}</td><td className="px-3 py-1.5">{i.email || '—'}</td>
                                        <td className="px-3 py-1.5">{i.erro ? <span className="text-red-700">{i.erro}</span> : 'Entra'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex gap-2 mt-3">
                        <Button variant="primary" disabled={conferencia.validos === 0} onClick={() => router.post('/flow/contatos/importar', { token: conferencia.token })}>Importar {conferencia.validos} contato(s)</Button>
                        <Button variant="secondary" onClick={() => router.get('/flow/contatos')}>Cancelar</Button>
                    </div>
                </Card>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <div className="lg:col-span-3 space-y-4">
                    <div className="flex gap-2 border-b border-gray-200">
                        {[['todos', 'Todos'], ['duplicados', 'Possíveis duplicados']].map(([k, r]) => (
                            <button key={k} type="button" onClick={() => ir({ aba: k })}
                                className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${filtros.aba === k ? 'border-cyan-600 text-cyan-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>{r}</button>
                        ))}
                    </div>

                    {filtros.aba === 'duplicados' ? (
                        <Card>
                            {duplicados.length === 0 ? <p className="text-sm text-gray-400">Nenhum possível duplicado encontrado (mesmo CPF, CNPJ, e-mail ou nome).</p> : (
                                <ul className="divide-y divide-gray-100">
                                    {duplicados.map((g, i) => (
                                        <li key={i} className="py-3 flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-xs font-semibold uppercase text-amber-700 mb-1">{g.motivo}</p>
                                                {g.contatos.map(c => (
                                                    <p key={c.id} className="text-sm"><Link href={`/flow/contatos/${c.id}`} className="text-blue-700 hover:underline">{c.nome}</Link>
                                                        <span className="text-xs text-gray-500"> · {tipos[c.tipo]} · {c.documento || 'sem documento'} · {c.email || 'sem e-mail'} · {ORIGEM[c.origem]}</span></p>
                                                ))}
                                            </div>
                                            <Button size="sm" variant="secondary" icon="fas fa-object-group" onClick={() => setUnificar({ contatos: g.contatos, principal: g.contatos[0].id })}>Unificar</Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    ) : (
                        <Card>
                            <form onSubmit={(e) => { e.preventDefault(); ir({ page: undefined }); }} className="flex flex-wrap gap-2 mb-4">
                                <input value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} className="ds-input flex-1 min-w-[220px]" placeholder="Nome, e-mail, CPF, CNPJ ou celular" />
                                <select value={f.tipo} onChange={(e) => setF({ ...f, tipo: e.target.value })} className="ds-input w-auto">
                                    <option value="">Todos os tipos</option>
                                    {Object.entries(tipos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                                </select>
                                <select value={f.lista} onChange={(e) => setF({ ...f, lista: e.target.value })} className="ds-input w-auto">
                                    <option value="">Todas as listas</option>
                                    {listas.map(l => <option key={l.id} value={l.id}>{l.nome}</option>)}
                                </select>
                                <Button type="submit" variant="secondary" icon="fas fa-magnifying-glass">Buscar</Button>
                            </form>

                            {marcados.length > 0 && (
                                <div className="mb-3 flex flex-wrap items-center gap-2 rounded bg-cyan-50 border border-cyan-100 px-3 py-2 text-sm">
                                    <span className="font-medium text-cyan-800">{marcados.length} marcado(s)</span>
                                    <select value={listaAlvo} onChange={(e) => setListaAlvo(e.target.value)} className="ds-input w-auto">
                                        <option value="">Lista…</option>
                                        {listas.map(l => <option key={l.id} value={l.id}>{l.nome}</option>)}
                                    </select>
                                    <Button size="sm" variant="secondary" disabled={! listaAlvo} onClick={() => itens('incluir', listaAlvo)}>Incluir na lista</Button>
                                    {filtros.lista && <Button size="sm" variant="secondary" onClick={() => itens('retirar', filtros.lista)}>Retirar desta lista</Button>}
                                    {selecionados.length >= 2 && <Button size="sm" variant="secondary" icon="fas fa-object-group" onClick={() => setUnificar({ contatos: selecionados, principal: selecionados[0].id })}>Unificar</Button>}
                                    <button type="button" className="text-xs text-gray-500 hover:underline ml-auto" onClick={() => setMarcados([])}>Desmarcar</button>
                                </div>
                            )}

                            {linhasPagina.length === 0 ? <p className="text-sm text-gray-400">Nenhum contato encontrado.</p> : (
                                <table className="w-full text-sm">
                                    <thead className="text-xs text-gray-500 border-b border-gray-100">
                                        <tr><th className="w-8" /><th className="py-2 text-left">Nome</th><th className="text-left">Tipo</th><th className="text-left">Documento</th><th className="text-left">E-mail / celular</th><th className="text-left">Origem</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {linhasPagina.map(c => (
                                            <tr key={c.id} className="hover:bg-gray-50">
                                                <td><input type="checkbox" checked={marcados.includes(c.id)} onChange={() => alternar(c.id)} className="rounded border-gray-300 text-cyan-600" /></td>
                                                <td className="py-2 pr-3"><Link href={`/flow/contatos/${c.id}`} className="text-blue-700 hover:underline font-medium">{c.nome}</Link>{c.empresa && <span className="text-xs text-gray-500"> · {c.empresa}</span>}</td>
                                                <td className="pr-3 text-gray-600">{tipos[c.tipo]}</td>
                                                <td className="pr-3 font-mono text-xs text-gray-600 whitespace-nowrap">{c.documento || '—'}</td>
                                                <td className="pr-3 text-gray-600 break-all">{c.email || c.celular || '—'}</td>
                                                <td className="text-xs text-gray-500">{ORIGEM[c.origem] || c.origem}{c.portal && <span className="ml-1 rounded-full bg-emerald-100 text-emerald-800 px-1.5 py-0.5 text-[10px] font-semibold">portal</span>}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                            {contatos.last_page > 1 && (
                                <div className="mt-4 flex flex-wrap gap-1">
                                    {contatos.links.map((l, i) => (
                                        <button key={i} type="button" disabled={! l.url} onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true })}
                                            className={`px-2.5 py-1 text-xs rounded border ${l.active ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-600 border-gray-200 disabled:opacity-40'}`}
                                            dangerouslySetInnerHTML={{ __html: l.label }} />
                                    ))}
                                </div>
                            )}
                            <p className="mt-2 text-xs text-gray-400">{contatos.total} contato(s)</p>
                        </Card>
                    )}
                </div>

                <Card title="Listas de contatos">
                    <ul className="divide-y divide-gray-100 mb-3">
                        {listas.length === 0 && <li className="py-2 text-sm text-gray-400">Nenhuma lista.</li>}
                        {listas.map(l => (
                            <li key={l.id} className="py-2 flex items-center justify-between gap-2">
                                <button type="button" className="text-sm text-left text-blue-700 hover:underline" onClick={() => { setF({ ...f, lista: String(l.id) }); ir({ lista: l.id, aba: 'todos' }); }}>{l.nome} <span className="text-xs text-gray-400">({l.total})</span></button>
                                <span className="flex gap-2 text-xs shrink-0">
                                    <button type="button" className="text-gray-500 hover:underline" onClick={() => { setEditandoLista(l); listaForm.setData({ nome: l.nome, descricao: l.descricao || '' }); }}>Editar</button>
                                    <button type="button" className="text-red-600 hover:underline" onClick={async () => (await confirmar({ titulo: `Excluir a lista "${l.nome}"?`, descricao: 'Os contatos continuam cadastrados.', tom: 'atencao', rotuloConfirmar: 'Excluir' })) && router.delete(`/flow/contatos/listas/${l.id}`)}>Excluir</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <form onSubmit={salvarLista} className="space-y-2">
                        <input value={listaForm.data.nome} onChange={(e) => listaForm.setData('nome', e.target.value)} maxLength={120} className="ds-input" placeholder={editandoLista ? 'Nome da lista' : 'Nova lista'} />
                        {listaForm.errors.nome && <p className="text-xs text-red-600">{listaForm.errors.nome}</p>}
                        <div className="flex gap-2">
                            <Button type="submit" size="sm" variant="primary" disabled={! listaForm.data.nome.trim()}>{editandoLista ? 'Salvar' : 'Criar lista'}</Button>
                            {editandoLista && <Button type="button" size="sm" variant="secondary" onClick={() => { setEditandoLista(null); listaForm.reset(); }}>Cancelar</Button>}
                        </div>
                    </form>
                    <p className="mt-3 text-[11px] text-gray-500">Para pôr contatos numa lista, marque-os na tabela.</p>
                </Card>
            </div>

            <Modal show={!! unificar} onClose={() => setUnificar(null)} title="Unificar contatos">
                {unificar && (
                    <div className="space-y-3">
                        {errors.outros && <p className="text-sm text-red-700">{errors.outros}</p>}
                        <p className="text-sm text-gray-600">Escolha o contato que fica. Os outros somem da lista e passam a apontar para ele: processos, procurações, listas e o cadastro do portal vão junto, e o que faltar no principal é completado com os dados dos outros.</p>
                        {unificar.contatos.map(c => (
                            <label key={c.id} className="flex items-start gap-2 text-sm cursor-pointer">
                                <input type="radio" name="principal" checked={unificar.principal === c.id} onChange={() => setUnificar({ ...unificar, principal: c.id })} className="mt-1" />
                                <span><b>{c.nome}</b> <span className="text-xs text-gray-500">#{c.id} · {tipos[c.tipo]} · {c.documento || 'sem documento'} · {c.email || 'sem e-mail'} · {ORIGEM[c.origem]}{c.portal ? ' · portal' : ''}</span></span>
                            </label>
                        ))}
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setUnificar(null)}>Cancelar</Button>
                            <Button variant="primary" onClick={() => router.post('/flow/contatos/unificar', { principal_id: unificar.principal, outros: unificar.contatos.map(c => c.id).filter(id => id !== unificar.principal) }, { onSuccess: () => { setUnificar(null); setMarcados([]); } })}>Unificar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
