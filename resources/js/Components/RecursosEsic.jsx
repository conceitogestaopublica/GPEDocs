/**
 * Recurso do requerente no e-SIC (LAI art. 15): os recursos interpostos com prazo e decisão de cada
 * instância, o registro do recurso que chegou fora do portal e a decisão da autoridade.
 */
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const dia = (s) => (s ? new Date(s).toLocaleDateString('pt-BR') : '');

export default function RecursosEsic({ processoId, dados, className = '' }) {
    const [modal, setModal] = useState(null);   // 'registrar' | {decidir: recurso}
    if (! dados) return null;
    const { recursos = [], cabe, motivo, pode_registrar, pode_decidir } = dados;
    if (! recursos.length && ! cabe) return null;

    return (
        <Card title="Recursos do requerente (e-SIC)" className={className}>
            {recursos.length === 0 && <p className="text-sm text-gray-400 mb-3">Nenhum recurso interposto.</p>}
            <ul className="space-y-3 mb-3">
                {recursos.map((r) => (
                    <li key={r.id} className="rounded-lg border border-gray-200 p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <b className="text-sm">{r.instancia}ª instância</b>
                            <span className="text-xs text-gray-500">interposto em {dia(r.interposto_em)} · {r.origem}{r.registrado_por ? ` · registrado por ${r.registrado_por}` : ''}</span>
                            {r.decisao
                                ? <span className={`ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold ${r.decisao_codigo === 'provido' ? 'bg-emerald-100 text-emerald-800' : r.decisao_codigo === 'nao_provido' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'}`}>{r.decisao}</span>
                                : <span className={`ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold ${r.atrasado ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'}`}>
                                    {r.atrasado ? 'Prazo de decisão vencido' : 'Aguardando decisão'} · até {dia(r.decidir_ate)}</span>}
                        </div>
                        <p className="mt-1 text-sm text-gray-700 whitespace-pre-wrap"><span className="text-gray-500">Motivo:</span> {r.motivo}</p>
                        {r.decisao && <p className="mt-1 text-sm text-gray-700 whitespace-pre-wrap"><span className="text-gray-500">Decisão de {r.decidido_por} em {dia(r.decidido_em)}:</span> {r.fundamentacao}</p>}
                        {! r.decisao && pode_decidir && <Button size="sm" className="mt-2" icon="fas fa-gavel" onClick={() => setModal({ decidir: r })}>Decidir</Button>}
                    </li>
                ))}
            </ul>
            {cabe
                ? <p className="text-xs text-gray-600 mb-2">Cabe recurso de <b>{cabe.instancia}ª instância</b> até {dia(cabe.ate)} (10 dias). Pelo portal o requerente recorre sozinho.</p>
                : motivo && recursos.length > 0 && <p className="text-xs text-gray-500">{motivo}</p>}
            {cabe && pode_registrar && <Button size="sm" variant="secondary" icon="fas fa-file-circle-plus" onClick={() => setModal('registrar')}>Registrar recurso recebido</Button>}

            <ModalRegistrar processoId={processoId} instancia={cabe?.instancia} origens={dados.origens} aberto={modal === 'registrar'} fechar={() => setModal(null)} />
            <ModalDecidir recurso={modal?.decidir} decisoes={dados.decisoes} fechar={() => setModal(null)} />
        </Card>
    );
}

function ModalRegistrar({ processoId, instancia, origens, aberto, fechar }) {
    const form = useForm({ motivo: '', origem: 'balcao' });
    const salvar = (e) => { e.preventDefault(); form.post(`/processos/${processoId}/recursos-esic`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } }); };
    return (
        <Modal show={aberto} onClose={fechar} title={`Registrar recurso de ${instancia}ª instância`}>
            <form onSubmit={salvar} className="space-y-3">
                <label className="block text-xs text-gray-600">Recebido por
                    <select value={form.data.origem} onChange={(e) => form.setData('origem', e.target.value)} className="ds-input mt-1">
                        {Object.entries(origens).filter(([k]) => k !== 'portal').map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                    </select></label>
                <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={4} className="ds-input" placeholder="Razões do recurso apresentadas pelo requerente" />
                {form.errors.motivo && <p className="text-xs text-red-600">{form.errors.motivo}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" loading={form.processing} disabled={! form.data.motivo.trim()}>Registrar</Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalDecidir({ recurso, decisoes, fechar }) {
    const form = useForm({ decisao: 'provido', fundamentacao: '' });
    const salvar = (e) => { e.preventDefault(); form.post(`/recursos-esic/${recurso.id}/decidir`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } }); };
    return (
        <Modal show={!! recurso} onClose={fechar} title={`Decidir recurso de ${recurso?.instancia || ''}ª instância`}>
            <form onSubmit={salvar} className="space-y-3">
                <div className="grid grid-cols-3 gap-2">
                    {Object.entries(decisoes).map(([k, r]) => (
                        <label key={k} className={`rounded-lg border-2 p-2 text-sm text-center cursor-pointer ${form.data.decisao === k ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200'}`}>
                            <input type="radio" className="hidden" checked={form.data.decisao === k} onChange={() => form.setData('decisao', k)} />{r}
                        </label>
                    ))}
                </div>
                <textarea value={form.data.fundamentacao} onChange={(e) => form.setData('fundamentacao', e.target.value)} rows={5} className="ds-input"
                    placeholder="Fundamentação (vai para o requerente). Se provido, informe aqui o acesso concedido ou como obtê-lo." />
                {form.errors.fundamentacao && <p className="text-xs text-red-600">{form.errors.fundamentacao}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-gavel" loading={form.processing} disabled={! form.data.fundamentacao.trim()}>Registrar decisão</Button>
                </div>
            </form>
        </Modal>
    );
}
