/**
 * Sistema tributário integrado: GPE Tributário, outro fornecedor (REST configurável) ou nenhum —
 * geral do município ou por UG — com teste de conexão, o mapeamento dos recursos e o endereço
 * de eventos que o tributário chama de volta.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

const VAZIO = { ug_id: '', driver: 'gpe', nome_sistema: '', base_url: '', auth_tipo: 'bearer', auth_header: '', credencial: '', config: '', timeout: 20, ativo: true };

export default function Index({ configuracoes = [], ugs = [], drivers = {}, recursos = {}, modelo_rest = '', campos = {}, eventos_url, eventos = [] }) {
    const errors = usePage().props.errors || {};
    const [f, setF] = useState(null);
    const campo = (k) => ({ value: f?.[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const salvar = () => {
        const dados = { ...f, ug_id: f.ug_id || null };
        f.id ? router.put(`/configuracoes/integracao-tributaria/${f.id}`, dados, { preserveScroll: true, onSuccess: () => setF(null) })
            : router.post('/configuracoes/integracao-tributaria', dados, { preserveScroll: true, onSuccess: () => setF(null) });
    };
    const ugNome = (id) => (id ? (ugs.find(u => u.id === id)?.nome || `UG ${id}`) : 'Todas as UGs');

    return (
        <AdminLayout>
            <Head title="Sistema tributário" />
            <PageHeader title="Sistema tributário" subtitle="Imóvel, guias, alvará, habite-se, certidão e fiscalização a partir dos processos">
                <Button icon="fas fa-plus" onClick={() => setF({ ...VAZIO })}>Nova configuração</Button>
            </PageHeader>

            <div className="space-y-3 mb-6">
                {configuracoes.length === 0 && <Card><p className="text-sm text-gray-500">Nenhuma configuração: os dados do tributário são informados à mão nos processos.</p></Card>}
                {configuracoes.map(c => (
                    <Card key={c.id}>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold text-gray-800">{c.nome_sistema || drivers[c.driver]}</span>
                            <span className="text-[11px] rounded-full bg-gray-100 text-gray-700 px-2 py-0.5">{drivers[c.driver]}</span>
                            <span className="text-[11px] rounded-full bg-cyan-50 text-cyan-800 px-2 py-0.5">{ugNome(c.ug_id)}</span>
                            {! c.ativo && <span className="text-[11px] rounded-full bg-red-50 text-red-700 px-2 py-0.5">inativa</span>}
                            <span className="ml-auto flex gap-2">
                                <Button size="sm" variant="secondary" icon="fas fa-plug-circle-check" onClick={() => router.post(`/configuracoes/integracao-tributaria/${c.id}/testar`, {}, { preserveScroll: true })}>Testar conexão</Button>
                                <Button size="sm" variant="secondary" icon="fas fa-pen" onClick={() => setF({ ...VAZIO, ...c, ug_id: c.ug_id || '', credencial: '' })}>Editar</Button>
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-gray-500 break-all">{c.base_url || 'sem endereço'} · autenticação {c.auth_tipo}{c.tem_credencial ? ' (credencial guardada)' : ''}</p>
                        <div className="mt-2 flex flex-wrap gap-1">
                            {Object.entries(recursos).map(([k, r]) => (
                                <span key={k} className={`text-[11px] rounded-full px-2 py-0.5 ${c.recursos.includes(k) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-50 text-gray-400 line-through'}`}>{r}</span>
                            ))}
                        </div>
                        {c.ultimo_teste && <p className={`mt-2 text-xs ${c.ultimo_teste.ok ? 'text-emerald-700' : 'text-red-600'}`}>Último teste ({new Date(c.ultimo_teste.em).toLocaleString('pt-BR')}): {c.ultimo_teste.msg}</p>}
                    </Card>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card title="Retorno do tributário (eventos)" subtitle="Guia paga, alvará emitido… o tributário avisa o GED aqui">
                    <p className="text-xs text-gray-600">POST <code className="text-cyan-700 break-all">{eventos_url}</code></p>
                    <p className="text-xs text-gray-600 mt-1">Autenticação: <code>Authorization: Bearer</code> com o token de um <a href="/configuracoes/sistemas-integrados" className="text-cyan-700 hover:underline">sistema integrado</a> criado para o tributário.</p>
                    <pre className="mt-2 text-[11px] bg-gray-50 rounded p-2 overflow-x-auto">{`{
  "evento": "guia.paga | guia.cancelada | guia.vencida | documento.emitido | documento.cancelado | fiscalizacao.atualizada",
  "tipo": "guia | alvara_construcao | habitese | certidao | fiscalizacao | licenca",
  "id_externo": "id no tributário",
  "processo": "nº do processo no GED (opcional)",
  "numero": "...", "situacao": "...", "valor": 0, "vencimento": "AAAA-MM-DD",
  "pdf_base64": "..." ou "pdf_url": "https://...",
  "dados": {}
}`}</pre>
                </Card>
                <Card title="Últimos eventos recebidos">
                    {eventos.length === 0 ? <p className="text-sm text-gray-400">Nenhum.</p> : (
                        <ul className="text-xs divide-y divide-gray-100">
                            {eventos.map((e, i) => <li key={i} className="py-1.5 flex gap-2"><span className="font-medium">{e.evento}</span><span className="text-gray-500">{e.sistema}</span>
                                <span className={e.resultado === 'aplicado' ? 'text-emerald-700' : 'text-amber-700'}>{e.resultado}</span><span className="ml-auto text-gray-400">{new Date(e.created_at).toLocaleString('pt-BR')}</span></li>)}
                        </ul>
                    )}
                </Card>
            </div>

            <Modal show={!! f} onClose={() => setF(null)} title={f?.id ? 'Editar integração' : 'Nova integração'} maxWidth="4xl">
                {f && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label className="block text-sm text-gray-700">Sistema<select {...campo('driver')} className="ds-input mt-1">{Object.entries(drivers).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                            <label className="block text-sm text-gray-700">Vale para<select {...campo('ug_id')} className="ds-input mt-1"><option value="">Todas as UGs</option>{ugs.map(u => <option key={u.id} value={u.id}>{u.codigo} — {u.nome}</option>)}</select></label>
                            <label className="block text-sm text-gray-700">Nome nas telas<input {...campo('nome_sistema')} className="ds-input mt-1" placeholder={drivers[f.driver]} /></label>
                        </div>
                        {errors.ug_id && <p className="text-xs text-red-600">{errors.ug_id}</p>}
                        {f.driver !== 'nenhum' && (
                            <>
                                <label className="block text-sm text-gray-700">Endereço da API<input {...campo('base_url')} className="ds-input mt-1" placeholder="https://tributario.municipio.gov.br/api" /></label>
                                {errors.base_url && <p className="text-xs text-red-600">{errors.base_url}</p>}
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                    <label className="block text-sm text-gray-700">Autenticação<select {...campo('auth_tipo')} className="ds-input mt-1">
                                        <option value="bearer">Bearer token</option><option value="header">Header próprio</option><option value="basic">Usuário e senha (Basic)</option><option value="nenhum">Nenhuma</option></select></label>
                                    {f.auth_tipo === 'header' && <label className="block text-sm text-gray-700">Nome do header<input {...campo('auth_header')} className="ds-input mt-1" placeholder="X-Api-Key" /></label>}
                                    {f.auth_tipo !== 'nenhum' && <label className="block text-sm text-gray-700 md:col-span-2">{f.auth_tipo === 'basic' ? 'usuario:senha' : 'Token'}<input type="password" {...campo('credencial')} className="ds-input mt-1" placeholder={f.tem_credencial ? 'em branco = manter a atual' : ''} autoComplete="new-password" /></label>}
                                    <label className="block text-sm text-gray-700">Tempo limite (s)<input type="number" min="3" max="120" {...campo('timeout')} className="ds-input mt-1" /></label>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm text-gray-700">{f.driver === 'rest' ? 'Recursos e mapeamento (JSON)' : 'Parâmetros do adaptador (JSON, opcional)'}</span>
                                        {f.driver === 'rest' && <button type="button" className="text-xs text-cyan-700 hover:underline" onClick={() => setF({ ...f, config: modelo_rest })}>usar modelo</button>}
                                    </div>
                                    <textarea {...campo('config')} rows={14} className="ds-input !h-auto mt-1 font-mono text-xs" spellCheck={false} />
                                    {errors.config && <p className="text-xs text-red-600">{errors.config}</p>}
                                    {f.driver === 'rest' && (
                                        <details className="mt-1 text-xs text-gray-600"><summary className="cursor-pointer">Campos que o GED entende</summary>
                                            {Object.entries(campos).map(([g, cs]) => <p key={g} className="mt-1"><b>{g}:</b> {Object.keys(cs).join(', ')}</p>)}
                                            <p className="mt-1">Marcadores aceitos no caminho e no corpo: {'{inscricao} {taxa} {valor} {documento} {nome} {processo} {tipo} {id} {motivo} {endereco} {area}'}</p>
                                        </details>
                                    )}
                                </div>
                            </>
                        )}
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! f.ativo} onChange={(e) => setF({ ...f, ativo: e.target.checked })} /> Ativa</label>
                        <div className="flex justify-end gap-2">
                            {f.id && <Button variant="danger" icon="fas fa-trash" onClick={() => router.delete(`/configuracoes/integracao-tributaria/${f.id}`, { onSuccess: () => setF(null) })}>Excluir</Button>}
                            <Button variant="secondary" onClick={() => setF(null)}>Cancelar</Button><Button onClick={salvar}>Salvar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}