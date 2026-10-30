/**
 * Sistema tributário no processo (TR 4.48.22, 4.48.42, 4.49): imóvel pela inscrição, guias,
 * alvará de construção / habite-se / certidão e fiscalização — pelo sistema configurado ou
 * informados à mão (com o PDF), quando não há integração para aquele recurso.
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const COR = { paga: 'bg-emerald-100 text-emerald-800', emitido: 'bg-emerald-100 text-emerald-800', emitida: 'bg-sky-100 text-sky-800',
    vencida: 'bg-red-100 text-red-700', cancelada: 'bg-gray-100 text-gray-600', cancelado: 'bg-gray-100 text-gray-600' };
const moeda = (v) => (v != null ? Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '');

export default function TributarioDoProcesso({ processoId, dados, anexos = [] }) {
    const [modal, setModal] = useState(null);   // { acao, ...campos }
    if (! dados) return null;
    const { sistema, recursos, documentos, tipos, vinculos, pode } = dados;
    const tem = (r) => recursos.includes(r);
    const base = `/processos/${processoId}/tributario`;
    const enviar = (url, corpo, arquivo = false) => router.post(url, corpo, { preserveScroll: true, forceFormData: arquivo, onSuccess: () => setModal(null) });
    const imovel = vinculos.find(v => v.tipo === 'imovel');
    const campo = (k) => ({ value: modal?.[k] ?? '', onChange: (e) => setModal({ ...modal, [k]: e.target.value }) });
    const nomeAnexo = (id) => anexos.find(a => a.id === id)?.nome;

    return (
        <Card title="Sistema tributário" subtitle={sistema} className="mb-6">
            {imovel ? (
                <div className="mb-3 rounded-lg bg-gray-50 p-3 text-sm">
                    <p className="font-semibold text-gray-800"><i className="fas fa-house mr-1 text-gray-400" />Inscrição {imovel.numero}{imovel.manual ? ' (informada)' : ''}</p>
                    <p className="text-xs text-gray-600 mt-0.5">{[imovel.dados.proprietario_nome, [imovel.dados.logradouro, imovel.dados.numero].filter(Boolean).join(', '), imovel.dados.bairro].filter(Boolean).join(' · ')}</p>
                    {(imovel.dados.area_terreno || imovel.dados.area_construida) && <p className="text-xs text-gray-500">Terreno {imovel.dados.area_terreno || '—'} m² · construída {imovel.dados.area_construida || '—'} m²{imovel.dados.zona ? ` · zona ${imovel.dados.zona}` : ''}</p>}
                </div>
            ) : <p className="text-sm text-gray-500 mb-3">Nenhum imóvel vinculado.</p>}

            {vinculos.filter(v => v.tipo !== 'imovel').length > 0 && (
                <ul className="divide-y divide-gray-100 text-sm mb-3">
                    {vinculos.filter(v => v.tipo !== 'imovel').map(v => (
                        <li key={v.id} className="py-2 flex flex-wrap items-center gap-2">
                            <span className="font-medium">{v.tipo_rotulo}</span>
                            <span className="text-gray-700">{v.numero || v.id_externo}</span>
                            {v.valor != null && <span className="text-gray-600">{moeda(v.valor)}</span>}
                            {v.vencimento && <span className="text-xs text-gray-500">vence {new Date(`${v.vencimento}T12:00:00`).toLocaleDateString('pt-BR')}</span>}
                            {v.situacao && <span className={`text-[11px] rounded-full px-2 py-0.5 ${COR[v.situacao] || 'bg-amber-100 text-amber-800'}`}>{v.situacao}</span>}
                            {v.manual && <span className="text-[10px] text-gray-400">informado à mão</span>}
                            {v.dados?.linha_digitavel && <span className="w-full text-[11px] text-gray-500 font-mono break-all">{v.dados.linha_digitavel}</span>}
                            <span className="ml-auto flex gap-2 text-xs">
                                {v.anexo_id && <span className="text-gray-500"><i className="fas fa-paperclip mr-1" />{nomeAnexo(v.anexo_id) || 'anexado'}</span>}
                                {pode && ! v.manual && v.id_externo && <button type="button" className="text-cyan-700 hover:underline" onClick={() => router.post(`${base}/${v.id}/atualizar`, {}, { preserveScroll: true })}>atualizar</button>}
                                {pode && <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(`${base}/${v.id}`, { preserveScroll: true })}>retirar</button>}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {pode && (
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" icon="fas fa-house" onClick={() => setModal({ acao: tem('imovel') ? 'imovel' : 'manual', tipo: 'imovel', inscricao: '', numero: '' })}>{imovel ? 'Trocar imóvel' : 'Vincular imóvel'}</Button>
                    <Button size="sm" variant="secondary" icon="fas fa-barcode" onClick={() => setModal({ acao: tem('guia_emitir') ? 'guia' : 'manual', tipo: 'guia', taxa: '', valor: '', numero: '' })}>{tem('guia_emitir') ? 'Emitir guia' : 'Informar guia'}</Button>
                    <Button size="sm" variant="secondary" icon="fas fa-file-contract" onClick={() => setModal({ acao: tem('documento_solicitar') ? 'documento' : 'manual', tipo: 'alvara_construcao', numero: '' })}>{tem('documento_solicitar') ? 'Pedir alvará / habite-se / certidão' : 'Informar alvará / habite-se'}</Button>
                    <Button size="sm" variant="secondary" icon="fas fa-person-digging" onClick={() => setModal({ acao: tem('fiscalizacao_abrir') ? 'fiscalizacao' : 'manual', tipo: 'fiscalizacao', motivo: '', numero: '' })}>{tem('fiscalizacao_abrir') ? 'Abrir fiscalização' : 'Informar fiscalização'}</Button>
                </div>
            )}

            <Modal show={!! modal} onClose={() => setModal(null)} title={modal?.acao === 'manual' ? `Informar ${tipos[modal?.tipo] || ''} (sem integração)` : sistema}>
                {modal && (
                    <div className="space-y-3">
                        {modal.acao === 'imovel' && <label className="block text-sm text-gray-700">Inscrição imobiliária<input {...campo('inscricao')} className="ds-input mt-1" autoFocus /></label>}
                        {modal.acao === 'guia' && <>
                            <label className="block text-sm text-gray-700">Taxa (código no tributário)<input {...campo('taxa')} className="ds-input mt-1" placeholder="Ex.: TAXA_APROVACAO_PROJETO" /></label>
                            <label className="block text-sm text-gray-700">Valor <span className="text-xs text-gray-400">(vazio = o tributário calcula)</span><input type="number" step="0.01" {...campo('valor')} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">Descrição<input {...campo('descricao')} className="ds-input mt-1" /></label>
                        </>}
                        {modal.acao === 'documento' && <>
                            <label className="block text-sm text-gray-700">Documento<select {...campo('tipo')} className="ds-input mt-1">{Object.entries(documentos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <div className="grid grid-cols-2 gap-3">
                                <label className="block text-sm text-gray-700">Área (m²)<input type="number" step="0.01" {...campo('area')} className="ds-input mt-1" /></label>
                                <label className="block text-sm text-gray-700">Registro (CREA/CAU)<input {...campo('registro_profissional')} className="ds-input mt-1" /></label>
                            </div>
                            <label className="block text-sm text-gray-700">Responsável técnico<input {...campo('responsavel_tecnico')} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">Observação<textarea {...campo('observacao')} rows={2} className="ds-input !h-auto mt-1" /></label>
                        </>}
                        {modal.acao === 'fiscalizacao' && <label className="block text-sm text-gray-700">Motivo<textarea {...campo('motivo')} rows={3} className="ds-input !h-auto mt-1" placeholder="Ex.: obra sem alvará denunciada pela ouvidoria" /></label>}
                        {modal.acao === 'manual' && <>
                            {modal.tipo !== 'imovel' && modal.tipo !== 'guia' && modal.tipo !== 'fiscalizacao' && (
                                <label className="block text-sm text-gray-700">Documento<select {...campo('tipo')} className="ds-input mt-1">{Object.entries(documentos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            )}
                            <label className="block text-sm text-gray-700">{modal.tipo === 'imovel' ? 'Inscrição imobiliária' : 'Número'}<input {...campo('numero')} className="ds-input mt-1" /></label>
                            {modal.tipo === 'guia' && <div className="grid grid-cols-2 gap-3">
                                <label className="block text-sm text-gray-700">Valor<input type="number" step="0.01" {...campo('valor')} className="ds-input mt-1" /></label>
                                <label className="block text-sm text-gray-700">Vencimento<input type="date" {...campo('vencimento')} className="ds-input mt-1" /></label></div>}
                            <label className="block text-sm text-gray-700">Situação<input {...campo('situacao')} className="ds-input mt-1" placeholder="emitida, paga, emitido…" /></label>
                            <label className="block text-sm text-gray-700">Observação<input {...campo('observacao')} className="ds-input mt-1" /></label>
                            {modal.tipo !== 'imovel' && <label className="block text-sm text-gray-700">PDF<input type="file" accept="application/pdf" onChange={(e) => setModal({ ...modal, pdf: e.target.files[0] })} className="block mt-1 text-sm" /></label>}
                        </>}
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setModal(null)}>Cancelar</Button>
                            <Button onClick={() => {
                                const { acao, ...c } = modal;
                                if (acao === 'imovel') enviar(`${base}/imovel`, { inscricao: c.inscricao });
                                if (acao === 'guia') enviar(`${base}/guias`, { taxa: c.taxa, valor: c.valor || null, descricao: c.descricao || null });
                                if (acao === 'documento') enviar(`${base}/documentos`, { tipo: c.tipo, area: c.area || null, responsavel_tecnico: c.responsavel_tecnico || null, registro_profissional: c.registro_profissional || null, observacao: c.observacao || null });
                                if (acao === 'fiscalizacao') enviar(`${base}/fiscalizacao`, { motivo: c.motivo });
                                if (acao === 'manual') enviar(`${base}/manual`, Object.fromEntries(Object.entries({ tipo: c.tipo, numero: c.numero, situacao: c.situacao, valor: c.valor, vencimento: c.vencimento, observacao: c.observacao, pdf: c.pdf }).filter(([, v]) => v)), !! c.pdf);
                            }}>Confirmar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}