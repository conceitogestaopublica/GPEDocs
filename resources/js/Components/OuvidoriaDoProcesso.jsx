/**
 * Ouvidoria e e-SIC na tela do processo (TR 4.33, 4.34): tipo da manifestação, identificação,
 * canal, prazo legal (com prorrogação justificada) e, na ouvidoria, a resposta que resolve em
 * todos os setores e vai ao manifestante como decisão final.
 */
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const PRAZO = {
    em_dia: ['No prazo', 'bg-emerald-100 text-emerald-800'], vencendo: ['Vence em breve', 'bg-amber-100 text-amber-800'],
    vencido: ['Prazo vencido', 'bg-red-100 text-red-700'], no_prazo: ['Respondida no prazo', 'bg-emerald-100 text-emerald-800'],
    fora_do_prazo: ['Respondida fora do prazo', 'bg-red-100 text-red-700'],
};
const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');

export default function OuvidoriaDoProcesso({ processoId, dados, className = '' }) {
    const [modal, setModal] = useState(null);
    const prorrogar = useForm({ motivo: '' });
    const resolver = useForm({ decisao: '' });
    if (! dados) return null;
    const [rotPrazo, corPrazo] = PRAZO[dados.prazo?.situacao] || [];
    const titulo = dados.modalidade === 'esic' ? 'Pedido de acesso à informação (LAI)' : 'Manifestação de ouvidoria';

    return (
        <Card title={titulo} className={className}>
            <div className="flex flex-wrap items-center gap-2 text-xs">
                {dados.manifestacao && <span className="rounded-full bg-violet-100 text-violet-800 px-2.5 py-0.5 font-semibold">{dados.manifestacao}</span>}
                {dados.modalidade === 'ouvidoria' && (
                    <span className={`rounded-full px-2.5 py-0.5 ${dados.identificacao === 'aberta' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800'}`}>
                        <i className={`fas ${dados.identificacao === 'aberta' ? 'fa-user' : dados.identificacao === 'sigilosa' ? 'fa-user-lock' : 'fa-user-secret'} mr-1`} />{dados.identificacao_rotulo}
                    </span>
                )}
                {dados.canal && <span className="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5"><i className="fas fa-right-to-bracket mr-1" />{dados.canal}</span>}
            </div>

            {dados.prazo && (
                <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <i className="fas fa-hourglass-half text-gray-400" />
                    <span>Prazo legal: <strong>{data(dados.prazo.vence_em)}</strong></span>
                    {rotPrazo && <span className={`text-[11px] rounded-full px-2 py-0.5 ${corPrazo}`}>{rotPrazo}{['em_dia', 'vencendo'].includes(dados.prazo.situacao) ? ` · ${dados.prazo.dias} dia(s)` : ''}</span>}
                    {dados.prorrogacao && <span className="text-[11px] text-gray-500">prorrogado em {data(dados.prorrogacao.em)}: {dados.prorrogacao.motivo}</span>}
                </div>
            )}

            {dados.decisao_final && (
                <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p className="text-xs font-semibold text-emerald-800 mb-1">Decisão final enviada em {data(dados.decisao_final.em)}</p>
                    <p className="text-sm text-gray-800 whitespace-pre-wrap">{dados.decisao_final.texto}</p>
                </div>
            )}

            {(dados.pode_prorrogar || dados.pode_resolver) && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {dados.pode_resolver && <Button size="sm" icon="fas fa-check-double" onClick={() => setModal('resolver')}>Resolver em todos os setores</Button>}
                    {dados.pode_prorrogar && <Button size="sm" variant="secondary" icon="fas fa-calendar-plus" onClick={() => setModal('prorrogar')}>Prorrogar prazo</Button>}
                </div>
            )}

            <Modal show={modal === 'prorrogar'} onClose={() => setModal(null)} title="Prorrogar o prazo">
                <form onSubmit={(e) => { e.preventDefault(); prorrogar.post(`/processos/${processoId}/prorrogar-prazo`, { preserveScroll: true, onSuccess: () => setModal(null) }); }} className="space-y-3">
                    <p className="text-sm text-gray-600">{dados.modalidade === 'esic' ? 'A LAI permite prorrogar uma vez, por mais 10 dias, com justificativa (art. 11 §2º).' : 'A Lei 13.460 permite prorrogar uma vez, por mais 30 dias, com justificativa (art. 16).'} O manifestante vê o novo prazo e o motivo.</p>
                    <textarea value={prorrogar.data.motivo} onChange={(e) => prorrogar.setData('motivo', e.target.value)} rows={3} className="ds-input" placeholder="Justificativa" />
                    {prorrogar.errors.motivo && <p className="text-xs text-red-600">{prorrogar.errors.motivo}</p>}
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancelar</Button>
                        <Button type="submit" loading={prorrogar.processing} disabled={! prorrogar.data.motivo.trim()}>Prorrogar</Button></div>
                </form>
            </Modal>

            <Modal show={modal === 'resolver'} onClose={() => setModal(null)} title="Resolver em todos os setores">
                <form onSubmit={(e) => { e.preventDefault(); resolver.post(`/processos/${processoId}/resolver-ouvidoria`, { preserveScroll: true, onSuccess: () => setModal(null) }); }} className="space-y-3">
                    <p className="text-sm text-gray-600">Encerra a manifestação em todos os setores em que ela está e envia esta resposta ao manifestante como decisão final (pelo portal ou pelo e-mail informado).</p>
                    <textarea value={resolver.data.decisao} onChange={(e) => resolver.setData('decisao', e.target.value)} rows={6} className="ds-input" placeholder="Resposta final ao manifestante" />
                    {resolver.errors.decisao && <p className="text-xs text-red-600">{resolver.errors.decisao}</p>}
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancelar</Button>
                        <Button type="submit" icon="fas fa-paper-plane" loading={resolver.processing} disabled={! resolver.data.decisao.trim()}>Resolver e enviar</Button></div>
                </form>
            </Modal>
        </Card>
    );
}
