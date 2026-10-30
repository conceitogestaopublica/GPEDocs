/**
 * Participantes do processo (TR 4.48.30, 4.48.31) — proprietário, arquiteto, engenheiro,
 * responsável técnico — com convite para confirmarem os próprios dados, e o que o externo
 * recebeu, abriu, viu e baixou (TR 4.48.28).
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const VAZIO = { papel: 'responsavel_tecnico', nome: '', cpf_cnpj: '', registro: '', email: '', telefone: '' };
const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : null);
const STATUS = { enviado: 'enviado', entregue: 'entregue', aberto: 'aberto', clicado: 'clicou no link', falhou: 'falhou' };

export default function ParticipantesDoProcesso({ processoId, dados, podeEditar }) {
    const [f, setF] = useState(null);
    if (! dados) return null;
    const { participantes, papeis, emails, acessos } = dados;
    const campo = (k) => ({ value: f?.[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const salvar = () => (f.id ? router.put(`/processos/${processoId}/participantes/${f.id}`, f, { preserveScroll: true, onSuccess: () => setF(null) })
        : router.post(`/processos/${processoId}/participantes`, f, { preserveScroll: true, onSuccess: () => setF(null) }));

    return (
        <Card title="Participantes e acesso do externo" className="mb-6">
            {participantes.length === 0 ? <p className="text-sm text-gray-400">Nenhum participante (proprietário, arquiteto, engenheiro, responsável técnico).</p> : (
                <ul className="divide-y divide-gray-100 text-sm">
                    {participantes.map(pt => (
                        <li key={pt.id} className="py-2 flex flex-wrap items-center gap-2">
                            <span className="text-[11px] rounded-full bg-indigo-50 text-indigo-800 px-2 py-0.5">{papeis[pt.papel] || pt.papel}</span>
                            <span className="font-medium">{pt.nome}</span>
                            {pt.registro && <span className="text-xs text-gray-500">{pt.registro}</span>}
                            {pt.email && <span className="text-xs text-gray-500">{pt.email}</span>}
                            {pt.confirmado_em ? <span className="text-[11px] text-emerald-700"><i className="fas fa-circle-check mr-1" />confirmou em {dt(pt.confirmado_em)}</span>
                                : pt.convite_enviado_em ? <span className="text-[11px] text-amber-700">convite enviado em {dt(pt.convite_enviado_em)}</span> : null}
                            {podeEditar && (
                                <span className="ml-auto flex gap-2 text-xs">
                                    {pt.email && ! pt.confirmado_em && <button type="button" className="text-cyan-700 hover:underline" onClick={() => router.post(`/processos/${processoId}/participantes/${pt.id}/convite`, {}, { preserveScroll: true })}>{pt.convite_enviado_em ? 'reenviar convite' : 'enviar convite'}</button>}
                                    <button type="button" className="text-gray-600 hover:underline" onClick={() => setF({ ...VAZIO, ...pt, cpf_cnpj: pt.cpf_cnpj || '', registro: pt.registro || '', email: pt.email || '', telefone: pt.telefone || '' })}>editar</button>
                                    <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(`/processos/${processoId}/participantes/${pt.id}`, { preserveScroll: true })}>retirar</button>
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {podeEditar && <div className="mt-2"><Button size="sm" variant="secondary" icon="fas fa-user-plus" onClick={() => setF({ ...VAZIO })}>Participante</Button></div>}

            {(emails.length > 0 || acessos.length > 0) && (
                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p className="text-xs font-semibold text-gray-600 mb-1">E-mails ao externo</p>
                        <ul className="text-xs space-y-1">
                            {emails.map((e, i) => (
                                <li key={i}><span className="text-gray-800">{e.assunto}</span> → {e.destinatario}
                                    <span className={`ml-1 rounded-full px-1.5 ${e.status === 'falhou' ? 'bg-red-50 text-red-700' : e.aberto_em ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{STATUS[e.status] || e.status}</span>
                                    {e.aberto_em && <span className="text-gray-400"> · aberto {dt(e.aberto_em)}</span>}</li>
                            ))}
                            {emails.length === 0 && <li className="text-gray-400">Nenhum.</li>}
                        </ul>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-gray-600 mb-1">No portal</p>
                        <ul className="text-xs space-y-1">
                            {acessos.map((a, i) => <li key={i}>{a.quem || 'Externo'} <b>{a.acao}</b>{a.anexo ? ` "${a.anexo}"` : ' o processo'} <span className="text-gray-400">· {dt(a.created_at)}</span></li>)}
                            {acessos.length === 0 && <li className="text-gray-400">Ainda não acessou.</li>}
                        </ul>
                    </div>
                </div>
            )}

            <Modal show={!! f} onClose={() => setF(null)} title={f?.id ? 'Editar participante' : 'Novo participante'}>
                {f && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <label className="block text-sm text-gray-700">Papel<select {...campo('papel')} className="ds-input mt-1">{Object.entries(papeis).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <label className="block text-sm text-gray-700">CPF/CNPJ<input {...campo('cpf_cnpj')} className="ds-input mt-1" /></label>
                        </div>
                        <label className="block text-sm text-gray-700">Nome<input {...campo('nome')} className="ds-input mt-1" /></label>
                        <div className="grid grid-cols-3 gap-3">
                            <label className="block text-sm text-gray-700">Registro (CREA/CAU)<input {...campo('registro')} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">E-mail<input {...campo('email')} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">Telefone<input {...campo('telefone')} className="ds-input mt-1" /></label>
                        </div>
                        <p className="text-[11px] text-gray-500">Com o e-mail, envie o convite: o participante confirma os dados e passa a acompanhar o processo pelo portal com o CPF/CNPJ.</p>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setF(null)}>Cancelar</Button><Button onClick={salvar} disabled={! f.nome.trim()}>Salvar</Button></div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}