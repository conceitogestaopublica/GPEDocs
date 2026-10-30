/**
 * Consulta prévia (TR 4.50): zonas com parâmetros urbanísticos e usos, camadas próprias
 * (GeoJSON), importação dos lotes (GeoJSON com o mapeamento das propriedades) e o mapa público.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

const ZONA_VAZIA = { nome: '', sigla: '', cor: '#0ea5e9', parametros: {}, usos: { permitidos: '', permissiveis: '', proibidos: '' }, observacao: '', ativo: true, arquivo: null };
const CAMADA_VAZIA = { nome: '', tipo: 'equipamentos', cor: '#7c3aed', visivel_padrao: false, ordem: 0, ativo: true, arquivo: null };
const paraForm = (o) => { const fd = new FormData(); const ir = (k, v) => { if (v === null || v === undefined) return; if (v instanceof File) fd.append(k, v); else if (typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => ir(`${k}[${kk}]`, vv)); else fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v); }; Object.entries(o).forEach(([k, v]) => ir(k, v)); return fd; };

export default function Index({ zonas = [], camadas = [], lotes, config, parametros = {}, tipos_camada: tiposCamada = {}, url_publica: urlPublica }) {
    const errors = usePage().props.errors || {};
    const [zona, setZona] = useState(null);
    const [camada, setCamada] = useState(null);
    const [imp, setImp] = useState(null);
    const [cfg, setCfg] = useState({ centro_lat: config?.centro_lat ?? '', centro_lng: config?.centro_lng ?? '', zoom: config?.zoom ?? 15, duvida_url: config?.duvida_url ?? '', aviso: config?.aviso ?? '' });
    const enviar = (url, dados, fechar) => router.post(url, paraForm(dados), { forceFormData: true, preserveScroll: true, onSuccess: () => fechar(null) });

    return (
        <AdminLayout>
            <Head title="Consulta prévia" />
            <PageHeader title="Consulta prévia (mapa urbano)" subtitle="Zonas e parâmetros urbanísticos, camadas e lotes do mapa público">
                <a href={urlPublica} target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-map">Abrir mapa público</Button></a>
            </PageHeader>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <Card title={`Zonas (${zonas.length})`}>
                    <ul className="divide-y divide-gray-100 text-sm">
                        {zonas.map((z) => (
                            <li key={z.id} className={`py-2 flex flex-wrap items-center gap-2 ${z.ativo ? '' : 'opacity-50'}`}>
                                <span className="h-4 w-4 rounded" style={{ background: z.cor }} />
                                <b>{z.sigla}</b> {z.nome}
                                <span className="text-xs text-gray-500">{z.lotes} lote(s){z.tem_geometria ? '' : ' · sem polígono'}</span>
                                <span className="ml-auto flex gap-2 text-xs">
                                    <button type="button" className="text-gray-600 hover:underline" onClick={() => setZona({ ...ZONA_VAZIA, ...z, usos: { permitidos: (z.usos.permitidos || []).join('\n'), permissiveis: (z.usos.permissiveis || []).join('\n'), proibidos: (z.usos.proibidos || []).join('\n') }, observacao: z.observacao || '', arquivo: null })}>editar</button>
                                    <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(`/configuracoes/urbanismo/zonas/${z.id}`, { preserveScroll: true })}>excluir</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <Button size="sm" className="mt-2" icon="fas fa-plus" onClick={() => setZona({ ...ZONA_VAZIA })}>Zona</Button>
                </Card>

                <Card title={`Camadas (${camadas.length})`} subtitle="Viário, bairros, APP/APA, equipamentos — GeoJSON">
                    <ul className="divide-y divide-gray-100 text-sm">
                        {camadas.map((c) => (
                            <li key={c.id} className={`py-2 flex flex-wrap items-center gap-2 ${c.ativo ? '' : 'opacity-50'}`}>
                                <span className="h-4 w-4 rounded" style={{ background: c.cor }} />
                                {c.nome} <span className="text-xs text-gray-500">{tiposCamada[c.tipo]} · {c.feicoes} feição(ões){c.visivel_padrao ? ' · aparece ao abrir' : ''}</span>
                                <span className="ml-auto flex gap-2 text-xs">
                                    <button type="button" className="text-gray-600 hover:underline" onClick={() => setCamada({ ...CAMADA_VAZIA, ...c, arquivo: null })}>editar</button>
                                    <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(`/configuracoes/urbanismo/camadas/${c.id}`, { preserveScroll: true })}>excluir</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <Button size="sm" className="mt-2" icon="fas fa-plus" onClick={() => setCamada({ ...CAMADA_VAZIA })}>Camada</Button>
                </Card>

                <Card title="Lotes" subtitle="GeoJSON (FeatureCollection de polígonos) exportado do cadastro ou do SIG">
                    <p className="text-sm">{lotes.total} lote(s) no mapa{lotes.sem_zona ? `, ${lotes.sem_zona} fora das zonas` : ''}.</p>
                    {errors.arquivo && <p className="text-xs text-red-600">{errors.arquivo}</p>}
                    <Button size="sm" className="mt-2" icon="fas fa-file-import" onClick={() => setImp({ arquivo: null, substituir: false, campos: { inscricao: 'inscricao', logradouro: 'logradouro', numero: 'numero', bairro: 'bairro', quadra: 'quadra', lote: 'lote', area: '' } })}>Importar lotes</Button>
                </Card>

                <Card title="Mapa público" subtitle={urlPublica}>
                    <div className="grid grid-cols-3 gap-3">
                        <label className="text-xs text-gray-600">Latitude do centro<input value={cfg.centro_lat} onChange={(e) => setCfg({ ...cfg, centro_lat: e.target.value })} className="ds-input mt-1" placeholder="(meio dos lotes)" /></label>
                        <label className="text-xs text-gray-600">Longitude<input value={cfg.centro_lng} onChange={(e) => setCfg({ ...cfg, centro_lng: e.target.value })} className="ds-input mt-1" /></label>
                        <label className="text-xs text-gray-600">Zoom inicial<input type="number" min="3" max="19" value={cfg.zoom} onChange={(e) => setCfg({ ...cfg, zoom: e.target.value })} className="ds-input mt-1" /></label>
                    </div>
                    <label className="mt-3 block text-xs text-gray-600">Link "Tenho uma dúvida" (portal; {'{inscricao}'} vira a inscrição do lote)
                        <input value={cfg.duvida_url} onChange={(e) => setCfg({ ...cfg, duvida_url: e.target.value })} className="ds-input mt-1" placeholder="https://portal.../servico/consulta-previa/solicitar?inscricao={inscricao}" /></label>
                    <label className="mt-3 block text-xs text-gray-600">Aviso legal<textarea value={cfg.aviso} onChange={(e) => setCfg({ ...cfg, aviso: e.target.value })} rows={2} className="ds-input !h-auto mt-1" placeholder="Informação orientativa; não substitui a certidão de uso do solo." /></label>
                    <Button size="sm" className="mt-3" icon="fas fa-save" onClick={() => router.post('/configuracoes/urbanismo/config', cfg, { preserveScroll: true })}>Salvar</Button>
                </Card>
            </div>

            <Modal show={!! zona} onClose={() => setZona(null)} title={zona?.id ? `Zona ${zona.sigla}` : 'Nova zona'} maxWidth="4xl">
                {zona && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-6 gap-3">
                            <label className="col-span-1 text-sm text-gray-700">Sigla<input value={zona.sigla} onChange={(e) => setZona({ ...zona, sigla: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="col-span-4 text-sm text-gray-700">Nome<input value={zona.nome} onChange={(e) => setZona({ ...zona, nome: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="col-span-1 text-sm text-gray-700">Cor<input type="color" value={zona.cor} onChange={(e) => setZona({ ...zona, cor: e.target.value })} className="mt-1 h-9 w-full" /></label>
                        </div>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            {Object.entries(parametros).map(([k, r]) => (
                                <label key={k} className="text-xs text-gray-600">{r}<input type="number" step="0.01" value={zona.parametros[k] ?? ''} onChange={(e) => setZona({ ...zona, parametros: { ...zona.parametros, [k]: e.target.value } })} className="ds-input mt-1" /></label>
                            ))}
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            {[['permitidos', 'Usos permitidos'], ['permissiveis', 'Usos permissíveis'], ['proibidos', 'Usos proibidos']].map(([k, r]) => (
                                <label key={k} className="text-xs text-gray-600">{r} <span className="text-gray-400">(um por linha)</span>
                                    <textarea value={zona.usos[k]} onChange={(e) => setZona({ ...zona, usos: { ...zona.usos, [k]: e.target.value } })} rows={4} className="ds-input !h-auto mt-1" /></label>
                            ))}
                        </div>
                        <label className="block text-xs text-gray-600">Observação<textarea value={zona.observacao} onChange={(e) => setZona({ ...zona, observacao: e.target.value })} rows={2} className="ds-input !h-auto mt-1" /></label>
                        <label className="block text-xs text-gray-600">Polígono da zona (GeoJSON){zona.tem_geometria ? ' — já tem; envie outro para trocar' : ''}<input type="file" accept=".geojson,.json" onChange={(e) => setZona({ ...zona, arquivo: e.target.files[0] || null })} className="block mt-1 text-sm" /></label>
                        {errors.arquivo && <p className="text-xs text-red-600">{errors.arquivo}</p>}
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! zona.ativo} onChange={(e) => setZona({ ...zona, ativo: e.target.checked })} /> Ativa</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setZona(null)}>Cancelar</Button>
                            <Button onClick={() => enviar(zona.id ? `/configuracoes/urbanismo/zonas/${zona.id}` : '/configuracoes/urbanismo/zonas',
                                { nome: zona.nome, sigla: zona.sigla, cor: zona.cor, parametros: zona.parametros, usos: zona.usos, observacao: zona.observacao, ativo: zona.ativo, arquivo: zona.arquivo }, setZona)}>Salvar</Button></div>
                    </div>
                )}
            </Modal>

            <Modal show={!! camada} onClose={() => setCamada(null)} title={camada?.id ? 'Editar camada' : 'Nova camada'}>
                {camada && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Nome<input value={camada.nome} onChange={(e) => setCamada({ ...camada, nome: e.target.value })} className="ds-input mt-1" /></label>
                        <div className="grid grid-cols-3 gap-3">
                            <label className="text-sm text-gray-700">Tipo<select value={camada.tipo} onChange={(e) => setCamada({ ...camada, tipo: e.target.value })} className="ds-input mt-1">{Object.entries(tiposCamada).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <label className="text-sm text-gray-700">Cor<input type="color" value={camada.cor} onChange={(e) => setCamada({ ...camada, cor: e.target.value })} className="mt-1 h-9 w-full" /></label>
                            <label className="text-sm text-gray-700">Ordem<input type="number" value={camada.ordem} onChange={(e) => setCamada({ ...camada, ordem: e.target.value })} className="ds-input mt-1" /></label>
                        </div>
                        <label className="block text-xs text-gray-600">Arquivo GeoJSON{camada.id ? ' (opcional: envie para trocar)' : ''}<input type="file" accept=".geojson,.json" onChange={(e) => setCamada({ ...camada, arquivo: e.target.files[0] || null })} className="block mt-1 text-sm" /></label>
                        {errors.arquivo && <p className="text-xs text-red-600">{errors.arquivo}</p>}
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! camada.visivel_padrao} onChange={(e) => setCamada({ ...camada, visivel_padrao: e.target.checked })} /> Aparece ao abrir o mapa</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setCamada(null)}>Cancelar</Button>
                            <Button onClick={() => enviar(camada.id ? `/configuracoes/urbanismo/camadas/${camada.id}` : '/configuracoes/urbanismo/camadas',
                                { nome: camada.nome, tipo: camada.tipo, cor: camada.cor, ordem: camada.ordem, visivel_padrao: camada.visivel_padrao, ativo: camada.ativo, arquivo: camada.arquivo }, setCamada)}>Salvar</Button></div>
                    </div>
                )}
            </Modal>

            <Modal show={!! imp} onClose={() => setImp(null)} title="Importar lotes">
                {imp && (
                    <div className="space-y-3">
                        <input type="file" accept=".geojson,.json" onChange={(e) => setImp({ ...imp, arquivo: e.target.files[0] || null })} className="block text-sm" />
                        <p className="text-xs text-gray-600">De qual propriedade de cada feição sai cada dado (a área, se vazia, é calculada pelo polígono):</p>
                        <div className="grid grid-cols-2 gap-2">
                            {Object.keys(imp.campos).map((k) => (
                                <label key={k} className="text-xs text-gray-600">{k}<input value={imp.campos[k]} onChange={(e) => setImp({ ...imp, campos: { ...imp.campos, [k]: e.target.value } })} className="ds-input mt-1" /></label>
                            ))}
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={imp.substituir} onChange={(e) => setImp({ ...imp, substituir: e.target.checked })} /> Apagar os lotes atuais antes</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setImp(null)}>Cancelar</Button>
                            <Button disabled={! imp.arquivo} onClick={() => enviar('/configuracoes/urbanismo/lotes', imp, setImp)}>Importar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
