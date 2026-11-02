/**
 * Configurar o licenciamento ambiental: serviço da Carta que recebe o requerimento, prazos de aviso,
 * atividades (potencial poluidor e faixas de porte) e documentos exigidos por modalidade e classe.
 */
import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

const POT = { P: 'Pequeno', M: 'Médio', G: 'Grande' };

export default function AmbientalConfiguracao({ config, servicos = [], atividades = [], documentos = [], modalidades = {} }) {
    const cfg = useForm({ servico_id: config?.servico_id || '', dias_aviso_vencimento: config?.dias_aviso_vencimento ?? 120, dias_aviso_condicionante: config?.dias_aviso_condicionante ?? 15 });
    const [atividade, setAtividade] = useState(null);
    const [documento, setDocumento] = useState(null);
    const salvar = (url, dados, fechar) => router.post(url, dados, { preserveScroll: true, onSuccess: () => fechar?.(null) });

    return (
        <AdminLayout>
            <Head title="Configurar licenciamento" />
            <PageHeader title="Configurar licenciamento ambiental" subtitle="Serviço do portal, prazos de aviso, atividades e documentos exigidos" />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card title="Recebimento e avisos">
                    <form onSubmit={(e) => { e.preventDefault(); cfg.post('/flow/ambiental/configuracao', { preserveScroll: true }); }} className="space-y-3">
                        <label className="block text-xs text-gray-600">Serviço da Carta que recebe o requerimento
                            <select value={cfg.data.servico_id} onChange={(e) => cfg.setData('servico_id', e.target.value)} className="ds-input mt-1">
                                <option value="">— escolha (precisa ter assunto e setor) —</option>
                                {servicos.map(s => <option key={s.id} value={s.id}>{s.titulo}</option>)}
                            </select></label>
                        <label className="block text-xs text-gray-600">Avisar o vencimento com antecedência de (dias)
                            <input type="number" min={1} max={365} value={cfg.data.dias_aviso_vencimento} onChange={(e) => cfg.setData('dias_aviso_vencimento', e.target.value)} className="ds-input mt-1 !w-28" />
                            <span className="block text-[11px] text-gray-400">A renovação da licença de operação deve ser pedida 120 dias antes (Res. CONAMA 237/97).</span></label>
                        <label className="block text-xs text-gray-600">Avisar condicionantes com antecedência de (dias)
                            <input type="number" min={1} max={180} value={cfg.data.dias_aviso_condicionante} onChange={(e) => cfg.setData('dias_aviso_condicionante', e.target.value)} className="ds-input mt-1 !w-28" /></label>
                        <Button type="submit" size="sm" icon="fas fa-save" loading={cfg.processing}>Salvar</Button>
                    </form>
                </Card>

                <Card title={`Atividades (${atividades.length})`} className="xl:col-span-2">
                    <table className="w-full text-sm mb-3">
                        <thead className="text-xs text-gray-500"><tr><th className="text-left py-1">Código</th><th className="text-left">Atividade</th><th className="text-left">Potencial</th><th className="text-left">Porte pequeno / médio até</th><th /></tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {atividades.map(a => (
                                <tr key={a.id} className={a.ativo ? '' : 'opacity-50'}>
                                    <td className="py-1.5 font-mono text-xs">{a.codigo}</td><td>{a.descricao}</td><td>{POT[a.potencial]}</td>
                                    <td className="text-xs">{Number(a.porte_pequeno_ate)} / {Number(a.porte_medio_ate)} {a.unidade}</td>
                                    <td className="text-right"><button type="button" onClick={() => setAtividade({ ...a, ativo: !! a.ativo })} className="text-xs text-cyan-700 hover:underline">editar</button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <Button size="sm" variant="secondary" icon="fas fa-plus" onClick={() => setAtividade({ codigo: '', descricao: '', potencial: 'M', unidade: 'ha', porte_pequeno_ate: '', porte_medio_ate: '', ativo: true })}>Atividade</Button>
                    <p className="mt-2 text-[11px] text-gray-500">Classe = porte × potencial poluidor: P/P 1, P/M 2, P/G 4; M/P 1, M/M 3, M/G 5; G/P 1, G/M 4, G/G 6 (referência DN COPAM 217/2017). A LAS só cabe nas classes 1 e 2.</p>
                </Card>

                <Card title={`Documentos exigidos (${documentos.length})`} className="xl:col-span-3">
                    <table className="w-full text-sm mb-3">
                        <thead className="text-xs text-gray-500"><tr><th className="text-left py-1">Documento</th><th className="text-left">Modalidades</th><th className="text-left">A partir da classe</th><th className="text-left">Obrigatório</th><th /></tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {documentos.map(d => (
                                <tr key={d.id} className={d.ativo ? '' : 'opacity-50'}>
                                    <td className="py-1.5">{d.nome}{d.extensoes && <span className="text-[11px] text-gray-400"> ({d.extensoes})</span>}</td>
                                    <td className="text-xs">{d.modalidades.join(', ')}</td><td>{d.classe_minima}</td><td>{d.obrigatorio ? 'sim' : 'não'}</td>
                                    <td className="text-right"><button type="button" onClick={() => setDocumento({ ...d, obrigatorio: !! d.obrigatorio, ativo: !! d.ativo })} className="text-xs text-cyan-700 hover:underline">editar</button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <Button size="sm" variant="secondary" icon="fas fa-plus" onClick={() => setDocumento({ nome: '', modalidades: ['LP', 'LI', 'LO'], classe_minima: 1, obrigatorio: true, extensoes: 'pdf', ordem: 0, ativo: true })}>Documento</Button>
                </Card>
            </div>

            <Modal show={!! atividade} onClose={() => setAtividade(null)} title={atividade?.id ? 'Editar atividade' : 'Nova atividade'}>
                {atividade && (
                    <form onSubmit={(e) => { e.preventDefault(); salvar(`/flow/ambiental/atividades${atividade.id ? `/${atividade.id}` : ''}`, atividade, setAtividade); }} className="space-y-2">
                        <div className="grid grid-cols-3 gap-2">
                            <input value={atividade.codigo} onChange={(e) => setAtividade({ ...atividade, codigo: e.target.value })} className="ds-input" placeholder="Código" />
                            <input value={atividade.descricao} onChange={(e) => setAtividade({ ...atividade, descricao: e.target.value })} className="ds-input col-span-2" placeholder="Descrição" />
                            <select value={atividade.potencial} onChange={(e) => setAtividade({ ...atividade, potencial: e.target.value })} className="ds-input">
                                {Object.entries(POT).map(([k, r]) => <option key={k} value={k}>Potencial {r}</option>)}</select>
                            <input value={atividade.unidade} onChange={(e) => setAtividade({ ...atividade, unidade: e.target.value })} className="ds-input" placeholder="Unidade (ha, m²…)" />
                            <span />
                            <input type="number" step="any" value={atividade.porte_pequeno_ate} onChange={(e) => setAtividade({ ...atividade, porte_pequeno_ate: e.target.value })} className="ds-input" placeholder="Pequeno até" />
                            <input type="number" step="any" value={atividade.porte_medio_ate} onChange={(e) => setAtividade({ ...atividade, porte_medio_ate: e.target.value })} className="ds-input" placeholder="Médio até" />
                            <label className="flex items-center gap-1 text-sm"><input type="checkbox" checked={atividade.ativo} onChange={(e) => setAtividade({ ...atividade, ativo: e.target.checked })} /> Ativa</label>
                        </div>
                        <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setAtividade(null)}>Cancelar</Button><Button type="submit">Salvar</Button></div>
                    </form>
                )}
            </Modal>
            <Modal show={!! documento} onClose={() => setDocumento(null)} title={documento?.id ? 'Editar documento' : 'Novo documento'}>
                {documento && (
                    <form onSubmit={(e) => { e.preventDefault(); salvar(`/flow/ambiental/documentos${documento.id ? `/${documento.id}` : ''}`, documento, setDocumento); }} className="space-y-2">
                        <input value={documento.nome} onChange={(e) => setDocumento({ ...documento, nome: e.target.value })} className="ds-input" placeholder="Nome do documento" />
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(modalidades).map(([k, r]) => (
                                <label key={k} className="flex items-center gap-1 text-xs" title={r}>
                                    <input type="checkbox" checked={documento.modalidades.includes(k)}
                                        onChange={(e) => setDocumento({ ...documento, modalidades: e.target.checked ? [...documento.modalidades, k] : documento.modalidades.filter(x => x !== k) })} />{k}
                                </label>
                            ))}
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            <label className="text-xs text-gray-600">A partir da classe<input type="number" min={1} max={6} value={documento.classe_minima} onChange={(e) => setDocumento({ ...documento, classe_minima: e.target.value })} className="ds-input mt-0.5" /></label>
                            <label className="text-xs text-gray-600">Extensões<input value={documento.extensoes || ''} onChange={(e) => setDocumento({ ...documento, extensoes: e.target.value })} className="ds-input mt-0.5" placeholder="pdf,jpg" /></label>
                            <label className="text-xs text-gray-600">Ordem<input type="number" value={documento.ordem} onChange={(e) => setDocumento({ ...documento, ordem: e.target.value })} className="ds-input mt-0.5" /></label>
                        </div>
                        <div className="flex gap-4 text-sm">
                            <label className="flex items-center gap-1"><input type="checkbox" checked={documento.obrigatorio} onChange={(e) => setDocumento({ ...documento, obrigatorio: e.target.checked })} /> Obrigatório</label>
                            <label className="flex items-center gap-1"><input type="checkbox" checked={documento.ativo} onChange={(e) => setDocumento({ ...documento, ativo: e.target.checked })} /> Ativo</label>
                        </div>
                        <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setDocumento(null)}>Cancelar</Button><Button type="submit">Salvar</Button></div>
                    </form>
                )}
            </Modal>
        </AdminLayout>
    );
}
