/**
 * Inscrições de um processo seletivo (TR 4.41): filtros, dados da ficha, documentos,
 * situação e resposta ao candidato (e-mail e portal) e exportação em CSV.
 */
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import { SITUACAO } from './Pss';

const COR = { recebida: 'bg-gray-100 text-gray-700', deferida: 'bg-emerald-100 text-emerald-800', indeferida: 'bg-red-100 text-red-700', classificada: 'bg-cyan-100 text-cyan-800' };

export default function PssShow({ pss, filtros = {}, situacoes = {}, campos = {}, resumo = {}, inscricoes }) {
    const [f, setF] = useState({ situacao: '', cargo: '', busca: '', ...filtros });
    const [resp, setResp] = useState(null);
    const limpo = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v));
    const [rot, cor] = SITUACAO[pss.situacao];

    return (
        <AdminLayout>
            <Head title={pss.titulo} />
            <PageHeader title={pss.titulo} subtitle={`${pss.edital_numero ? `Edital ${pss.edital_numero} · ` : ''}Ficha: ${pss.assunto || 'só os dados do candidato'}`}>
                <span className={`text-xs rounded-full px-2.5 py-1 ${cor}`}>{rot}</span>
                <a href={`/flow/pss/${pss.id}/csv?${new URLSearchParams(limpo({ situacao: filtros.situacao, cargo: filtros.cargo }))}`}><Button variant="secondary" icon="fas fa-file-csv">Exportar CSV</Button></a>
            </PageHeader>

            <div className="flex flex-wrap gap-2 mb-3 text-xs">
                {Object.entries(situacoes).map(([v, r]) => <span key={v} className={`rounded-full px-2.5 py-0.5 ${COR[v]}`}>{r}: {resumo[v] || 0}</span>)}
            </div>

            <form onSubmit={(e) => { e.preventDefault(); router.get(`/flow/pss/${pss.id}`, limpo(f), { preserveScroll: true }); }}
                className="bg-white rounded-xl border border-gray-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                <label className="text-xs text-gray-600">Situação<select value={f.situacao} onChange={(e) => setF({ ...f, situacao: e.target.value })} className="ds-input mt-1">
                    <option value="">Todas</option>{Object.entries(situacoes).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">Cargo<select value={f.cargo} onChange={(e) => setF({ ...f, cargo: e.target.value })} className="ds-input mt-1">
                    <option value="">Todos</option>{pss.cargos.map(c => <option key={c.nome} value={c.nome}>{c.nome}</option>)}</select></label>
                <label className="text-xs text-gray-600">Busca<input value={f.busca} onChange={(e) => setF({ ...f, busca: e.target.value })} className="ds-input mt-1" placeholder="Nome, inscrição ou CPF" /></label>
                <div className="flex gap-2"><Button type="submit" icon="fas fa-filter">Filtrar</Button><Button type="button" variant="secondary" onClick={() => router.get(`/flow/pss/${pss.id}`)}>Limpar</Button></div>
            </form>

            <div className="space-y-3">
                {inscricoes.data.length === 0 && <p className="text-center text-gray-400 py-8">Nenhuma inscrição.</p>}
                {inscricoes.data.map(i => (
                    <div key={i.id} className="bg-white rounded-xl border border-gray-200 p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold text-gray-800">{i.numero}</span>
                            <span className="text-gray-700">{i.nome}</span>
                            <span className="text-xs text-gray-500">CPF {i.cpf}{i.email ? ` · ${i.email}` : ''}{i.telefone ? ` · ${i.telefone}` : ''}</span>
                            {i.cargo && <span className="text-[11px] rounded-full bg-violet-100 text-violet-800 px-2 py-0.5">{i.cargo}</span>}
                            <span className={`text-[11px] rounded-full px-2 py-0.5 ${COR[i.situacao]}`}>{situacoes[i.situacao]}</span>
                            <Button size="sm" variant="secondary" icon="fas fa-reply" className="ml-auto" onClick={() => setResp({ id: i.id, numero: i.numero, situacao: i.situacao, resposta: i.resposta || '' })}>Responder</Button>
                        </div>
                        {Object.keys(i.dados).length > 0 && (
                            <dl className="mt-2 grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-1 text-xs">
                                {Object.entries(i.dados).map(([k, v]) => (
                                    <div key={k}><dt className="text-gray-500 inline">{campos[k] || k}: </dt><dd className="inline text-gray-800">{Array.isArray(v) ? v.join(', ') : typeof v === 'object' && v ? Object.values(v).filter(Boolean).join(', ') : typeof v === 'boolean' ? (v ? 'Sim' : 'Não') : String(v ?? '')}</dd></div>
                                ))}
                            </dl>
                        )}
                        {i.anexos.length > 0 && (
                            <p className="mt-2 text-xs">{i.anexos.map(a => <a key={a.id} href={`/flow/pss/${pss.id}/anexos/${a.id}`} className="mr-3 text-cyan-700 hover:underline"><i className="fas fa-paperclip mr-1" />{a.nome}</a>)}</p>
                        )}
                        {i.resposta && <p className="mt-2 text-xs text-gray-600 bg-gray-50 rounded p-2 whitespace-pre-wrap">{i.resposta}</p>}
                    </div>
                ))}
            </div>

            <Modal show={!! resp} onClose={() => setResp(null)} title={`Responder ${resp?.numero || ''}`}>
                {resp && (
                    <form onSubmit={(e) => { e.preventDefault(); router.post(`/flow/pss/${pss.id}/inscricoes/${resp.id}/resposta`, { situacao: resp.situacao, resposta: resp.resposta }, { preserveScroll: true, onSuccess: () => setResp(null) }); }} className="space-y-3">
                        <select value={resp.situacao} onChange={(e) => setResp({ ...resp, situacao: e.target.value })} className="ds-input">
                            {Object.entries(situacoes).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                        </select>
                        <textarea value={resp.resposta} onChange={(e) => setResp({ ...resp, resposta: e.target.value })} rows={4} className="ds-input !h-auto" placeholder="Mensagem ao candidato (vai por e-mail e aparece no portal)" />
                        <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setResp(null)}>Cancelar</Button><Button type="submit" icon="fas fa-paper-plane">Enviar</Button></div>
                    </form>
                )}
            </Modal>
        </AdminLayout>
    );
}