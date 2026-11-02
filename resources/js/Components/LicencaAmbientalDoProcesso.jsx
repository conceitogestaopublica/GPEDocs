/**
 * Licença ambiental do processo: enquadramento do requerimento (no balcão), dados da licença, emissão com
 * condicionantes e validade, indeferimento e cumprimento das condicionantes.
 */
import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const dia = (s) => (s ? new Date(`${String(s).slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const COR = { em_analise: 'bg-amber-100 text-amber-800', emitida: 'bg-emerald-100 text-emerald-800', indeferida: 'bg-red-100 text-red-700',
    vencida: 'bg-gray-200 text-gray-700', renovada: 'bg-sky-100 text-sky-800' };

export default function LicencaAmbientalDoProcesso({ processoId, dados, className = '' }) {
    const [modal, setModal] = useState(null);   // emitir | indeferir | {cumprir: condicionante}
    if (! dados) return null;
    const l = dados.licenca;

    if (! l) {
        return dados.pode_gerir ? (
            <Card title="Licença ambiental" className={className}>
                <p className="text-sm text-gray-600 mb-3">Requerimento recebido no balcão: informe a atividade e o porte para enquadrar e ver os documentos exigidos.</p>
                <FormEnquadrar processoId={processoId} dados={dados} />
            </Card>
        ) : null;
    }

    return (
        <Card title={`Licença ambiental${l.numero ? ` ${l.numero}` : ''}`} className={className}>
            <div className="flex flex-wrap items-center gap-2 mb-3">
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${COR[l.status]}`}>{l.status_rotulo}</span>
                <span className="text-sm font-medium text-gray-800">{l.modalidade_rotulo}</span>
                <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-800">Classe {l.classe}</span>
                {l.anterior && <span className="text-xs text-gray-500">renovação da {l.anterior}</span>}
            </div>
            <dl className="grid grid-cols-1 gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                <div><dt className="inline text-gray-500">Atividade: </dt><dd className="inline">{l.atividade}</dd></div>
                <div><dt className="inline text-gray-500">Enquadramento: </dt><dd className="inline">porte {l.porte} ({l.porte_valor} {l.unidade}), potencial {l.potencial}</dd></div>
                <div><dt className="inline text-gray-500">Empreendedor: </dt><dd className="inline">{l.empreendedor}{l.documento ? ` — ${l.documento}` : ''}</dd></div>
                <div><dt className="inline text-gray-500">Empreendimento: </dt><dd className="inline">{l.empreendimento || '—'}{l.endereco ? `, ${l.endereco}` : ''}</dd></div>
                {l.emitida_em && <div><dt className="inline text-gray-500">Emitida em: </dt><dd className="inline">{dia(l.emitida_em)} · válida até <b>{dia(l.valida_ate)}</b></dd></div>}
                {l.motivo_indeferimento && <div className="md:col-span-2"><dt className="inline text-gray-500">Motivo do indeferimento: </dt><dd className="inline">{l.motivo_indeferimento}</dd></div>}
            </dl>
            {(l.documento_id || l.verificacao_url) && (
                <div className="mt-2 flex flex-wrap gap-3 text-xs">
                    {l.documento_id && <a href={`/documentos/${l.documento_id}`} className="text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />PDF da licença (assinar / baixar)</a>}
                    {l.verificacao_url && <a href={l.verificacao_url} target="_blank" rel="noreferrer" className="text-cyan-700 hover:underline"><i className="fas fa-qrcode mr-1" />Verificação pública</a>}
                </div>
            )}
            {l.condicionantes.length > 0 && (
                <div className="mt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Condicionantes</p>
                    <ul className="divide-y divide-gray-100">
                        {l.condicionantes.map((c) => (
                            <li key={c.id} className="py-1.5 text-sm flex items-start gap-2">
                                <i className={`fas ${c.cumprida_em ? 'fa-circle-check text-emerald-600' : 'fa-circle text-gray-300'} mt-1 text-xs`} />
                                <div className="flex-1">
                                    <p>{c.descricao}{c.prazo && <span className="text-xs text-gray-500"> · prazo {dia(c.prazo)}</span>}</p>
                                    {c.cumprida_em && <p className="text-xs text-emerald-700">Cumprida em {dia(c.cumprida_em)}: {c.cumprimento}</p>}
                                </div>
                                {! c.cumprida_em && dados.pode_gerir && l.status === 'emitida' && (
                                    <button type="button" onClick={() => setModal({ cumprir: c })} className="text-xs text-cyan-700 hover:underline">registrar cumprimento</button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {dados.pode_gerir && l.status === 'em_analise' && (
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button size="sm" icon="fas fa-stamp" onClick={() => setModal('emitir')}>Emitir licença</Button>
                    <Button size="sm" variant="danger" icon="fas fa-ban" onClick={() => setModal('indeferir')}>Indeferir</Button>
                </div>
            )}
            <ModalEmitir licenca={l} validade={dados.validade} aberto={modal === 'emitir'} fechar={() => setModal(null)} />
            <ModalTexto aberto={modal === 'indeferir'} fechar={() => setModal(null)} titulo="Indeferir requerimento" url={`/flow/ambiental/licencas/${l.id}/indeferir`}
                campo="motivo" placeholder="Motivo do indeferimento (vai para o empreendedor)" rotulo="Indeferir" perigo />
            <ModalTexto aberto={!! modal?.cumprir} fechar={() => setModal(null)} titulo="Cumprimento da condicionante" url={modal?.cumprir ? `/flow/ambiental/condicionantes/${modal.cumprir.id}/cumprir` : ''}
                campo="cumprimento" placeholder="Como foi cumprida (relatório, vistoria, documento apresentado…)" rotulo="Registrar" />
        </Card>
    );
}

function FormEnquadrar({ processoId, dados }) {
    const form = useForm({ modalidade: 'LO', atividade_id: '', porte_valor: '', empreendedor_nome: '', empreendedor_documento: '', empreendedor_email: '', empreendimento_nome: '', empreendimento_endereco: '' });
    const [enq, setEnq] = useState(null);
    useEffect(() => {
        const { atividade_id, porte_valor, modalidade } = form.data;
        if (! atividade_id || porte_valor === '') { setEnq(null); return undefined; }
        const t = setTimeout(() => fetch(`/flow/ambiental/enquadrar?atividade_id=${atividade_id}&porte_valor=${porte_valor}&modalidade=${modalidade}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then(setEnq).catch(() => {}), 300);
        return () => clearTimeout(t);
    }, [form.data.atividade_id, form.data.porte_valor, form.data.modalidade]);
    const campo = (k, ph, extra = {}) => <input value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} placeholder={ph} className="ds-input" {...extra} />;
    const salvar = (e) => { e.preventDefault(); form.post(`/flow/ambiental/processos/${processoId}/requerimento`, { preserveScroll: true }); };
    const unidade = dados.atividades.find((a) => String(a.id) === String(form.data.atividade_id))?.unidade;

    return (
        <form onSubmit={salvar} className="space-y-2">
            <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                <select value={form.data.modalidade} onChange={(e) => form.setData('modalidade', e.target.value)} className="ds-input">
                    {Object.entries(dados.modalidades).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                </select>
                <select value={form.data.atividade_id} onChange={(e) => form.setData('atividade_id', e.target.value)} className="ds-input md:col-span-2">
                    <option value="">Atividade…</option>
                    {dados.atividades.map((a) => <option key={a.id} value={a.id}>{a.codigo} — {a.descricao}</option>)}
                </select>
                {campo('porte_valor', `Porte${unidade ? ` (${unidade})` : ''}`, { type: 'number', min: 0, step: 'any' })}
                {campo('empreendedor_nome', 'Empreendedor')}
                {campo('empreendedor_documento', 'CPF/CNPJ')}
                {campo('empreendedor_email', 'E-mail do empreendedor', { type: 'email' })}
                {campo('empreendimento_nome', 'Nome do empreendimento')}
                {campo('empreendimento_endereco', 'Endereço do empreendimento')}
            </div>
            {enq && (enq.erro
                ? <p className="text-xs text-red-600">{enq.erro}</p>
                : <div className="rounded-lg bg-green-50 p-2 text-xs text-green-900">
                    Porte {enq.porte_rotulo}, potencial poluidor {enq.potencial_rotulo} → <b>classe {enq.classe}</b>.
                    {enq.documentos.length > 0 && <> Documentos exigidos: {enq.documentos.map((d) => `${d.nome}${d.obrigatorio ? '' : ' (opcional)'}`).join('; ')}.</>}
                </div>)}
            {Object.values(form.errors)[0] && <p className="text-xs text-red-600">{Object.values(form.errors)[0]}</p>}
            <Button type="submit" size="sm" icon="fas fa-leaf" loading={form.processing} disabled={! enq || enq.erro || ! form.data.empreendedor_nome.trim()}>Enquadrar requerimento</Button>
        </form>
    );
}

function ModalEmitir({ licenca, validade, aberto, fechar }) {
    const form = useForm({ validade_anos: validade[licenca.modalidade] || 10, condicionantes: [{ descricao: '', prazo: '' }] });
    const setCond = (i, k, v) => form.setData('condicionantes', form.data.condicionantes.map((c, j) => (j === i ? { ...c, [k]: v } : c)));
    const salvar = (e) => { e.preventDefault(); form.post(`/flow/ambiental/licencas/${licenca.id}/emitir`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } }); };
    return (
        <Modal show={aberto} onClose={fechar} title={`Emitir ${licenca.modalidade_rotulo}`} maxWidth="2xl">
            <form onSubmit={salvar} className="space-y-3">
                <label className="block text-xs text-gray-600">Validade (anos)
                    <input type="number" min={1} max={10} value={form.data.validade_anos} onChange={(e) => form.setData('validade_anos', e.target.value)} className="ds-input mt-1 !w-28" /></label>
                <p className="text-xs font-medium text-gray-700">Condicionantes</p>
                {form.data.condicionantes.map((c, i) => (
                    <div key={i} className="flex gap-2">
                        <textarea value={c.descricao} onChange={(e) => setCond(i, 'descricao', e.target.value)} rows={2} className="ds-input flex-1" placeholder={`Condicionante ${i + 1}`} />
                        <input type="date" value={c.prazo} onChange={(e) => setCond(i, 'prazo', e.target.value)} className="ds-input !w-40" title="Prazo (opcional)" />
                    </div>
                ))}
                <button type="button" onClick={() => form.setData('condicionantes', [...form.data.condicionantes, { descricao: '', prazo: '' }])} className="text-xs text-cyan-700 hover:underline">+ condicionante</button>
                <p className="text-[11px] text-gray-500">Gera o PDF da licença com QR de verificação e uma assinatura pendente para você; o processo é encerrado como deferido e o empreendedor é avisado.</p>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-stamp" loading={form.processing}>Emitir</Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalTexto({ aberto, fechar, titulo, url, campo, placeholder, rotulo, perigo = false }) {
    const [texto, setTexto] = useState('');
    const [enviando, setEnviando] = useState(false);
    const salvar = (e) => {
        e.preventDefault();
        setEnviando(true);
        router.post(url, { [campo]: texto }, { preserveScroll: true, onFinish: () => setEnviando(false), onSuccess: () => { setTexto(''); fechar(); } });
    };
    return (
        <Modal show={aberto} onClose={fechar} title={titulo}>
            <form onSubmit={salvar} className="space-y-3">
                <textarea value={texto} onChange={(e) => setTexto(e.target.value)} rows={4} className="ds-input" placeholder={placeholder} />
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" variant={perigo ? 'danger' : 'primary'} loading={enviando} disabled={! texto.trim()}>{rotulo}</Button>
                </div>
            </form>
        </Modal>
    );
}
