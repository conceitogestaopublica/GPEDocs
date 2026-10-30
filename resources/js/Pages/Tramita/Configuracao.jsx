/**
 * Configuração do Tramita GOV.BR (TR 4.28.1–4.28.4): adaptador (simulador para a homologação interna ou o
 * barramento real), ambiente, URL e certificado do ente (mTLS), repositório e estrutura recebidos na adesão,
 * assunto e unidade de entrada dos recebidos, aceite automático; testar conexão e simular um ente enviando.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

export default function Configuracao({ config, tipos, setores }) {
    const errors = usePage().props.errors || {};
    const [c, setC] = useState({ ...config, url: config.url || '', repositorio_id: config.repositorio_id || '', estrutura_id: config.estrutura_id || '',
        estrutura_nome: config.estrutura_nome || '', tipo_recebido_id: config.tipo_recebido_id || '', unidade_entrada_id: config.unidade_entrada_id || '', certificado: null, certificado_senha: '' });
    const campo = (k) => ({ value: c[k] ?? '', onChange: (e) => setC({ ...c, [k]: e.target.value }) });
    const salvar = () => router.post('/tramita/configuracao', { ...c, ativo: c.ativo ? 1 : 0, aceite_automatico: c.aceite_automatico ? 1 : 0 }, { forceFormData: true, preserveScroll: true });

    return (
        <AdminLayout>
            <Head title="Tramita GOV.BR — configuração" />
            <PageHeader title="Tramita GOV.BR — configuração" subtitle="Adesão do ente ao barramento (Portaria SEGES/ME 9.412/2022)" />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card title="Conexão">
                    <div className="space-y-3">
                        <label className="flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={c.ativo} onChange={(e) => setC({ ...c, ativo: e.target.checked })} /> Tramita GOV.BR ativo</label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-xs text-gray-600">Adaptador<select {...campo('adaptador')} className="ds-input mt-1">
                                <option value="simulador">Simulador (homologação interna)</option><option value="pen">Barramento real (PEN)</option></select></label>
                            <label className="text-xs text-gray-600">Ambiente<select {...campo('ambiente')} className="ds-input mt-1">
                                <option value="homologacao">Homologação</option><option value="producao">Produção</option></select></label>
                        </div>
                        {c.adaptador === 'pen' && (
                            <>
                                <label className="block text-xs text-gray-600">URL do barramento<input {...campo('url')} className="ds-input mt-1" placeholder="(informada na adesão)" /></label>
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="text-xs text-gray-600">Certificado do ente (.pem / .pfx){config.tem_certificado && <span className="ml-1 text-emerald-700">— já enviado</span>}
                                        <input type="file" accept=".pem,.pfx,.p12,.crt" onChange={(e) => setC({ ...c, certificado: e.target.files[0] })} className="mt-1 block w-full text-xs" /></label>
                                    <label className="text-xs text-gray-600">Senha do certificado<input type="password" {...campo('certificado_senha')} className="ds-input mt-1" placeholder={config.tem_certificado ? '(mantida)' : ''} /></label>
                                </div>
                                <p className="rounded-lg bg-amber-50 p-2 text-xs text-amber-900">A conexão (URL + certificado) já pode ser testada. As operações do barramento real são ativadas na homologação, com a especificação oficial entregue na adesão — até lá, use o simulador.</p>
                            </>
                        )}
                        <div className="grid grid-cols-3 gap-3">
                            <label className="text-xs text-gray-600">Repositório<input {...campo('repositorio_id')} className="ds-input mt-1" /></label>
                            <label className="text-xs text-gray-600">Estrutura do ente<input {...campo('estrutura_id')} className="ds-input mt-1" /></label>
                            <label className="text-xs text-gray-600">Nome do ente<input {...campo('estrutura_nome')} className="ds-input mt-1" placeholder="Prefeitura Municipal de …" /></label>
                        </div>
                    </div>
                </Card>
                <Card title="Recebidos de outros entes">
                    <div className="space-y-3">
                        <label className="block text-xs text-gray-600">Abrir como o assunto<select {...campo('tipo_recebido_id')} className="ds-input mt-1"><option value="">—</option>
                            {tipos.map((t) => <option key={t.id} value={t.id}>{t.nome}</option>)}</select></label>
                        <label className="block text-xs text-gray-600">Na unidade (protocolo de entrada)<select {...campo('unidade_entrada_id')} className="ds-input mt-1"><option value="">—</option>
                            {setores.map((s) => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}</select></label>
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={c.aceite_automatico} onChange={(e) => setC({ ...c, aceite_automatico: e.target.checked })} /> Aceitar sozinho (sem conferência do protocolo)</label>
                        <p className="text-xs text-gray-500">Sem aceite automático, os servidores da unidade de entrada são avisados e aceitam ou recusam em Tramita GOV.BR → Recebidos.</p>
                    </div>
                </Card>
            </div>
            {Object.values(errors)[0] && <p className="mt-3 text-sm text-red-600">{Object.values(errors)[0]}</p>}
            <div className="mt-4 flex flex-wrap gap-2">
                <Button icon="fas fa-save" onClick={salvar}>Salvar</Button>
                <Button variant="secondary" icon="fas fa-plug" onClick={() => router.post('/tramita/configuracao/testar', {}, { preserveScroll: true })}>Testar conexão</Button>
                {config.adaptador === 'simulador' && config.ativo && (
                    <>
                        <Button variant="secondary" icon="fas fa-inbox" onClick={() => router.post('/tramita/configuracao/simular', { tipo: 'oficio' }, { preserveScroll: true })}>Simular: ente envia um ofício</Button>
                        <Button variant="secondary" icon="fas fa-folder" onClick={() => router.post('/tramita/configuracao/simular', { tipo: 'processo' }, { preserveScroll: true })}>Simular: ente envia um processo</Button>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
