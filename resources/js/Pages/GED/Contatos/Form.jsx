/**
 * Cadastro do contato (TR 4.21): dados conforme o perfil (PF, PJ, setor de PJ), regras de
 * preenchimento, campos complementares, procurações (outorgadas e recebidas), listas,
 * processos em que aparece e histórico.
 */
import { useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import EnderecoForm from '../../../Components/EnderecoForm';
import { useConfirm } from '../../../Components/ConfirmProvider';

const ACOES = { cadastro: 'Cadastrado', alteracao: 'Alterado', importacao: 'Importado', unificacao: 'Recebeu contatos unificados',
    unificado_em: 'Unificado em outro', procuracao: 'Procuração registrada', procuracao_revogada: 'Procuração revogada', exclusao: 'Excluído' };
const ORIGEM = { interno: 'cadastro interno', portal: 'autocadastro no portal', processo: 'requerente de processo', importacao: 'importação' };
const dataBr = (d) => (d ? new Date(d.length === 10 ? `${d}T12:00:00` : d).toLocaleDateString('pt-BR') : '');

/** Procura contato pelo nome/documento (empresa do setor, procurador). */
function BuscaContato({ tipo = '', valor, rotulo, onEscolher, excluir }) {
    const [q, setQ] = useState('');
    const [achados, setAchados] = useState([]);
    useEffect(() => {
        if (q.trim().length < 2) { setAchados([]); return; }
        const t = setTimeout(() => fetch(`/flow/contatos/buscar?${new URLSearchParams({ q, tipo })}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json()).then(l => setAchados(l.filter(c => c.id !== excluir))), 300);
        return () => clearTimeout(t);
    }, [q, tipo, excluir]);
    return (
        <div className="relative">
            {valor ? (
                <div className="flex items-center justify-between ds-input">
                    <span className="truncate">{rotulo}</span>
                    <button type="button" className="text-xs text-gray-500 hover:underline" onClick={() => onEscolher(null)}>trocar</button>
                </div>
            ) : (
                <input value={q} onChange={(e) => setQ(e.target.value)} className="ds-input" placeholder="Digite nome, CPF ou CNPJ…" />
            )}
            {! valor && achados.length > 0 && (
                <ul className="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded shadow max-h-60 overflow-y-auto">
                    {achados.map(c => (
                        <li key={c.id}><button type="button" className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                            onClick={() => { onEscolher(c); setQ(''); setAchados([]); }}>{c.nome} <span className="text-xs text-gray-500">{c.documento || c.email || ''}</span></button></li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function CampoComplementar({ campo, valor, onChange, erro }) {
    const tipoInput = { numero: 'number', data: 'date', email: 'email', telefone: 'tel' }[campo.formato] || 'text';
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{campo.nome}{campo.obrigatorio && <span className="text-red-600"> *</span>}</label>
            {campo.formato === 'lista' ? (
                <select value={valor || ''} onChange={(e) => onChange(e.target.value)} className="ds-input">
                    <option value="">—</option>
                    {(campo.opcoes || []).map(o => <option key={o} value={o}>{o}</option>)}
                </select>
            ) : (
                <input type={tipoInput} value={valor || ''} onChange={(e) => onChange(e.target.value)} className="ds-input" />
            )}
            {campo.explicacao && <p className="mt-1 text-[11px] text-gray-500">{campo.explicacao}</p>}
            {erro && <p className="mt-1 text-xs text-red-600">{erro}</p>}
        </div>
    );
}

export default function ContatoForm({ contato = null, tipos = {}, campos = [], regras = {}, procuracoes = [], procurador_de = [], setores = [], processos = [], listas = [], nas_listas = [], historico = [] }) {
    const confirmar = useConfirm();
    const form = useForm({
        tipo: contato?.tipo || 'pf', nome: contato?.nome || '', cpf: contato?.cpf || '', cnpj: contato?.cnpj || '',
        email: contato?.email || '', celular: contato?.celular || '', telefone: contato?.telefone || '',
        endereco: contato?.endereco || {}, empresa_id: contato?.empresa_id || '', observacao: contato?.observacao || '',
        campos: contato?.campos || {},
    });
    const [empresa, setEmpresa] = useState(contato?.empresa_id ? { id: contato.empresa_id, nome: contato.empresa_nome } : null);
    const d = form.data;
    const obrig = (campo) => (regras[d.tipo] || []).includes(campo);
    const R = ({ c }) => (obrig(c) ? <span className="text-red-600"> *</span> : null);
    const erro = (k) => form.errors[k] && <p className="mt-1 text-xs text-red-600">{form.errors[k]}</p>;
    const camposDoPerfil = campos.filter(c => ! c.perfis || c.perfis.length === 0 || c.perfis.includes(d.tipo));
    const enderecoSet = (k, v) => form.setData('endereco', typeof k === 'object' ? k : { ...d.endereco, [k]: v });

    const salvar = (e) => {
        e.preventDefault();
        contato ? form.put(`/flow/contatos/${contato.id}`, { preserveScroll: true }) : form.post('/flow/contatos');
    };

    const proc = useForm({ procurador_id: '', poderes: '', inicio: '', fim: '', documento: null });
    const [procurador, setProcurador] = useState(null);
    const registrarProcuracao = (e) => {
        e.preventDefault();
        proc.post(`/flow/contatos/${contato.id}/procuracoes`, { forceFormData: true, preserveScroll: true, onSuccess: () => { proc.reset(); setProcurador(null); } });
    };

    return (
        <AdminLayout>
            <Head title={contato ? contato.nome : 'Novo contato'} />
            <PageHeader title={contato ? contato.nome : 'Novo contato'} subtitle={contato ? `Contato #${contato.id} · origem: ${ORIGEM[contato.origem] || contato.origem}${contato.portal ? ' · tem acesso ao portal' : ''}` : 'Pessoa física, jurídica ou setor de jurídica'}>
                <Button variant="secondary" icon="fas fa-arrow-left" href="/flow/contatos">Voltar</Button>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title="Dados" className="lg:col-span-2">
                    <form onSubmit={salvar} className="space-y-4">
                        <div className="flex flex-wrap gap-4">
                            {Object.entries(tipos).map(([k, r]) => (
                                <label key={k} className="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" checked={d.tipo === k} onChange={() => form.setData('tipo', k)} />{r}
                                </label>
                            ))}
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">{d.tipo === 'pf' ? 'Nome' : d.tipo === 'pj' ? 'Razão social' : 'Nome do setor'}<span className="text-red-600"> *</span></label>
                                <input value={d.nome} onChange={(e) => form.setData('nome', e.target.value)} className="ds-input" />
                                {erro('nome')}
                            </div>
                            {d.tipo === 'setor_pj' && (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Empresa<R c="empresa_id" /></label>
                                    <BuscaContato tipo="pj" valor={empresa?.id} rotulo={empresa?.nome} excluir={contato?.id}
                                        onEscolher={(c) => { setEmpresa(c); form.setData('empresa_id', c?.id || ''); }} />
                                    {erro('empresa_id')}
                                </div>
                            )}
                            {d.tipo === 'pf' ? (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">CPF<R c="cpf" /></label>
                                    <input value={d.cpf} onChange={(e) => form.setData('cpf', e.target.value)} className="ds-input font-mono" placeholder="000.000.000-00" />
                                    {erro('cpf')}
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">CNPJ<R c="cnpj" /></label>
                                    <input value={d.cnpj} onChange={(e) => form.setData('cnpj', e.target.value)} className="ds-input font-mono" placeholder="00.000.000/0000-00" />
                                    {erro('cnpj')}
                                </div>
                            )}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">E-mail<R c="email" /></label>
                                <input type="email" value={d.email} onChange={(e) => form.setData('email', e.target.value)} className="ds-input" />
                                {erro('email')}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Celular<R c="celular" /></label>
                                <input value={d.celular} onChange={(e) => form.setData('celular', e.target.value)} className="ds-input" placeholder="(00) 00000-0000" />
                                {erro('celular')}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Telefone<R c="telefone" /></label>
                                <input value={d.telefone} onChange={(e) => form.setData('telefone', e.target.value)} className="ds-input" />
                                {erro('telefone')}
                            </div>
                        </div>
                        <p className="text-[11px] text-gray-500">É preciso ao menos um destes: e-mail, CPF, CNPJ ou celular.</p>

                        <div>
                            <p className="text-sm font-medium text-gray-700 mb-2">Endereço<R c="endereco" /></p>
                            <EnderecoForm data={d.endereco || {}} setData={enderecoSet} />
                            {erro('endereco')}
                        </div>

                        {camposDoPerfil.length > 0 && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                                {camposDoPerfil.map(c => (
                                    <CampoComplementar key={c.id} campo={c} valor={d.campos[c.id]} erro={form.errors[`campos.${c.id}`]}
                                        onChange={(v) => form.setData('campos', { ...d.campos, [c.id]: v })} />
                                ))}
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                            <textarea value={d.observacao} onChange={(e) => form.setData('observacao', e.target.value)} rows={2} className="ds-input" />
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" variant="primary" loading={form.processing}>{contato ? 'Salvar' : 'Cadastrar'}</Button>
                            {contato && (
                                <Button type="button" variant="danger" onClick={async () => (await confirmar({ titulo: 'Excluir este contato?', descricao: 'Contato em processo ou com acesso ao portal não pode ser excluído; para juntar duplicados, use a unificação.', tom: 'perigo', rotuloConfirmar: 'Excluir' }))
                                    && router.delete(`/flow/contatos/${contato.id}`)}>Excluir</Button>
                            )}
                        </div>
                    </form>
                </Card>

                <div className="space-y-6">
                    {contato && (
                        <Card title="Listas">
                            {listas.length === 0 ? <p className="text-sm text-gray-400">Nenhuma lista criada.</p> : listas.map(l => {
                                const esta = nas_listas.includes(l.id);
                                return (
                                    <label key={l.id} className="flex items-center gap-2 text-sm py-1 cursor-pointer">
                                        <input type="checkbox" checked={esta} className="rounded border-gray-300 text-cyan-600"
                                            onChange={() => router.post(`/flow/contatos/listas/${l.id}/itens`, { contatos: [contato.id], acao: esta ? 'retirar' : 'incluir' }, { preserveScroll: true })} />
                                        {l.nome}
                                    </label>
                                );
                            })}
                        </Card>
                    )}
                    {contato && d.tipo === 'pj' && (
                        <Card title={`Setores (${setores.length})`}>
                            {setores.length === 0 ? <p className="text-sm text-gray-400">Nenhum setor cadastrado para esta empresa.</p> : (
                                <ul className="text-sm space-y-1">{setores.map(s => <li key={s.id}><Link href={`/flow/contatos/${s.id}`} className="text-blue-700 hover:underline">{s.nome}</Link></li>)}</ul>
                            )}
                        </Card>
                    )}
                    {contato && (
                        <Card title={`Processos (${processos.length})`}>
                            {processos.length === 0 ? <p className="text-sm text-gray-400">Não aparece em processos.</p> : (
                                <ul className="text-sm divide-y divide-gray-100">
                                    {processos.map(p => <li key={p.id} className="py-1.5"><Link href={`/processos/${p.id}`} className="text-blue-700 hover:underline font-mono text-xs">{p.numero_protocolo}</Link> <span className="text-gray-600">{p.assunto}</span></li>)}
                                </ul>
                            )}
                        </Card>
                    )}
                </div>
            </div>

            {contato && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <Card title="Procurações dadas por este contato">
                        {procuracoes.length === 0 ? <p className="text-sm text-gray-400 mb-3">Nenhuma procuração.</p> : (
                            <ul className="divide-y divide-gray-100 mb-4">
                                {procuracoes.map(p => (
                                    <li key={p.id} className="py-2 text-sm flex items-start justify-between gap-3">
                                        <div>
                                            <p><Link href={`/flow/contatos/${p.procurador_id}`} className="text-blue-700 hover:underline font-medium">{p.procurador}</Link>
                                                <span className={`ml-2 text-[10px] font-semibold rounded-full px-2 py-0.5 ${p.vigente ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'}`}>{p.revogada_em ? 'Revogada' : p.vigente ? 'Vigente' : 'Vencida'}</span></p>
                                            <p className="text-xs text-gray-500">{p.inicio ? `de ${dataBr(p.inicio)} ` : ''}{p.fim ? `até ${dataBr(p.fim)}` : 'sem prazo'}{p.poderes ? ` · ${p.poderes}` : ''}</p>
                                            {p.documento_path && <a href={`/flow/contatos/procuracoes/${p.id}/documento`} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline">Ver documento</a>}
                                        </div>
                                        {! p.revogada_em && <button type="button" className="text-xs text-red-600 hover:underline shrink-0" onClick={async () => (await confirmar({ titulo: 'Revogar a procuração?', tom: 'atencao', rotuloConfirmar: 'Revogar' })) && router.post(`/flow/contatos/procuracoes/${p.id}/revogar`, {}, { preserveScroll: true })}>Revogar</button>}
                                    </li>
                                ))}
                            </ul>
                        )}
                        <form onSubmit={registrarProcuracao} className="space-y-3 border-t border-gray-100 pt-3">
                            <p className="text-xs font-semibold uppercase text-gray-500">Nova procuração</p>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Procurador</label>
                                <BuscaContato valor={procurador?.id} rotulo={procurador?.nome} excluir={contato.id}
                                    onEscolher={(c) => { setProcurador(c); proc.setData('procurador_id', c?.id || ''); }} />
                                {proc.errors.procurador_id && <p className="mt-1 text-xs text-red-600">{proc.errors.procurador_id}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Poderes</label>
                                <input value={proc.data.poderes} onChange={(e) => proc.setData('poderes', e.target.value)} maxLength={1000} className="ds-input" placeholder="Ex.: protocolar e acompanhar processos de obras" />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div><label className="block text-sm font-medium text-gray-700 mb-1">Início</label><input type="date" value={proc.data.inicio} onChange={(e) => proc.setData('inicio', e.target.value)} className="ds-input" /></div>
                                <div><label className="block text-sm font-medium text-gray-700 mb-1">Fim</label><input type="date" value={proc.data.fim} onChange={(e) => proc.setData('fim', e.target.value)} className="ds-input" />{proc.errors.fim && <p className="mt-1 text-xs text-red-600">{proc.errors.fim}</p>}</div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Documento da procuração <span className="text-xs text-gray-400">(PDF ou imagem, opcional)</span></label>
                                <input type="file" accept=".pdf,image/*" onChange={(e) => proc.setData('documento', e.target.files?.[0] || null)} className="text-sm" />
                            </div>
                            <Button type="submit" variant="primary" size="sm" loading={proc.processing} disabled={! proc.data.procurador_id}>Registrar procuração</Button>
                        </form>
                    </Card>

                    <div className="space-y-6">
                        <Card title="É procurador de">
                            {procurador_de.length === 0 ? <p className="text-sm text-gray-400">Não é procurador de ninguém.</p> : (
                                <ul className="divide-y divide-gray-100">
                                    {procurador_de.map(p => (
                                        <li key={p.id} className="py-2 text-sm">
                                            <Link href={`/flow/contatos/${p.outorgante_id}`} className="text-blue-700 hover:underline font-medium">{p.outorgante}</Link>
                                            <span className={`ml-2 text-[10px] font-semibold rounded-full px-2 py-0.5 ${p.vigente ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'}`}>{p.revogada_em ? 'Revogada' : p.vigente ? 'Vigente' : 'Vencida'}</span>
                                            <p className="text-xs text-gray-500">{p.fim ? `até ${dataBr(p.fim)}` : 'sem prazo'}{p.poderes ? ` · ${p.poderes}` : ''}</p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                        <Card title="Histórico">
                            {historico.length === 0 ? <p className="text-sm text-gray-400">Sem registros.</p> : (
                                <ul className="divide-y divide-gray-100 text-xs">
                                    {historico.map((h, i) => <li key={i} className="py-1.5"><b>{ACOES[h.acao] || h.acao}</b> · {new Date(h.created_at).toLocaleString('pt-BR')} · {h.usuario || 'Sistema'}</li>)}
                                </ul>
                            )}
                        </Card>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
