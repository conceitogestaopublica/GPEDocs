/**
 * Fiscalização no processo (TR 4.49): de qualquer processo (ex.: denúncia da ouvidoria) gera a
 * fiscalização ligada a ele; no processo de fiscalização, registra diligências com constatação,
 * resultado, fotos/plantas e o ponto do GPS.
 */
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const COR = { regular: 'bg-emerald-100 text-emerald-800', irregular: 'bg-red-100 text-red-700', notificado: 'bg-amber-100 text-amber-800',
    autuado: 'bg-red-100 text-red-700', embargado: 'bg-red-200 text-red-800', nao_localizado: 'bg-gray-100 text-gray-700' };
const agora = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };

export default function FiscalizacaoDoProcesso({ processoId, dados, unidades = [], pode }) {
    const [gerar, setGerar] = useState(null);
    const [dil, setDil] = useState(null);
    const [gps, setGps] = useState('');
    if (! dados) return null;
    const { origem, geradas, eh_fiscalizacao: ehFisc, diligencias, resultados, tipos_fiscalizacao: tipos } = dados;
    if (! origem && ! geradas.length && ! ehFisc && ! pode) return null;
    const pegarGps = () => {
        if (! navigator.geolocation) { setGps('Navegador sem localização.'); return; }
        setGps('Obtendo localização…');
        navigator.geolocation.getCurrentPosition((pos) => { setDil((x) => ({ ...x, latitude: pos.coords.latitude.toFixed(7), longitude: pos.coords.longitude.toFixed(7) })); setGps(`Precisão ~${Math.round(pos.coords.accuracy)} m`); },
            () => setGps('Não foi possível obter a localização.'), { enableHighAccuracy: true, timeout: 15000 });
    };

    return (
        <Card title="Fiscalização" className="mb-6">
            {origem && <p className="text-sm mb-2"><i className="fas fa-link mr-1 text-gray-400" />Gerada a partir de <Link href={`/processos/${origem.id}`} className="text-cyan-700 hover:underline">{origem.numero_protocolo}</Link> — {origem.assunto}</p>}
            {geradas.length > 0 && <p className="text-sm mb-2"><i className="fas fa-person-digging mr-1 text-gray-400" />Fiscalizações: {geradas.map((g, i) => <span key={g.id}>{i ? ', ' : ''}<Link href={`/processos/${g.id}`} className="text-cyan-700 hover:underline">{g.numero_protocolo}</Link> ({g.status})</span>)}</p>}

            {ehFisc && (
                <div className="mt-2">
                    <p className="text-sm font-medium text-gray-700 mb-1">Diligências</p>
                    {diligencias.length === 0 ? <p className="text-sm text-gray-400">Nenhuma diligência registrada.</p> : (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {diligencias.map((d) => (
                                <li key={d.id} className="py-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{new Date(d.realizada_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</span>
                                        <span className={`text-[11px] rounded-full px-2 py-0.5 ${COR[d.resultado] || 'bg-gray-100'}`}>{d.resultado_rotulo}</span>
                                        <span className="text-xs text-gray-500">{d.fiscal}</span>
                                        {d.latitude && <a className="text-xs text-cyan-700 hover:underline" target="_blank" rel="noreferrer" href={`https://www.openstreetmap.org/?mlat=${d.latitude}&mlon=${d.longitude}#map=19/${d.latitude}/${d.longitude}`}><i className="fas fa-location-dot mr-1" />no mapa</a>}
                                        {d.anexos.length > 0 && <span className="text-xs text-gray-500"><i className="fas fa-camera mr-1" />{d.anexos.length} arquivo(s)</span>}
                                    </div>
                                    <p className="text-gray-700 whitespace-pre-wrap mt-0.5">{d.constatacao}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {pode && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {ehFisc && <Button size="sm" icon="fas fa-clipboard-check" onClick={() => { setGps(''); setDil({ realizada_em: agora(), resultado: 'irregular', constatacao: '', latitude: '', longitude: '', arquivos: [] }); }}>Registrar diligência</Button>}
                    {! ehFisc && tipos.length > 0 && <Button size="sm" variant="secondary" icon="fas fa-person-digging" onClick={() => setGerar({ tipo_processo_id: tipos[0].id, setor_destino: '', motivo: '', copiar_anexos: true })}>Gerar fiscalização</Button>}
                </div>
            )}

            <Modal show={!! gerar} onClose={() => setGerar(null)} title="Gerar fiscalização">
                {gerar && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Assunto<select value={gerar.tipo_processo_id} onChange={(e) => setGerar({ ...gerar, tipo_processo_id: e.target.value })} className="ds-input mt-1">
                            {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}</select></label>
                        <label className="block text-sm text-gray-700">Setor de fiscalização<select value={gerar.setor_destino} onChange={(e) => setGerar({ ...gerar, setor_destino: e.target.value })} className="ds-input mt-1">
                            <option value="">Escolha…</option>{unidades.map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}</select></label>
                        <textarea value={gerar.motivo} onChange={(e) => setGerar({ ...gerar, motivo: e.target.value })} rows={3} className="ds-input !h-auto" placeholder="O que deve ser verificado" />
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={gerar.copiar_anexos} onChange={(e) => setGerar({ ...gerar, copiar_anexos: e.target.checked })} /> Copiar os anexos (fotos da denúncia etc.)</label>
                        <p className="text-[11px] text-gray-500">Local e mapa vêm do processo de origem. Denunciante sigiloso ou anônimo não é copiado.</p>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setGerar(null)}>Cancelar</Button>
                            <Button disabled={! gerar.setor_destino || ! gerar.motivo.trim()} onClick={() => router.post(`/processos/${processoId}/fiscalizacao-interna`, gerar)}>Abrir fiscalização</Button></div>
                    </div>
                )}
            </Modal>

            <Modal show={!! dil} onClose={() => setDil(null)} title="Registrar diligência">
                {dil && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <label className="block text-sm text-gray-700">Data e hora<input type="datetime-local" value={dil.realizada_em} onChange={(e) => setDil({ ...dil, realizada_em: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">Resultado<select value={dil.resultado} onChange={(e) => setDil({ ...dil, resultado: e.target.value })} className="ds-input mt-1">
                                {Object.entries(resultados).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                        </div>
                        <textarea value={dil.constatacao} onChange={(e) => setDil({ ...dil, constatacao: e.target.value })} rows={4} className="ds-input !h-auto" placeholder="O que foi constatado no local" />
                        <div>
                            <label className="block text-sm text-gray-700">Fotos, plantas (imagem, PDF, DWG)</label>
                            <input type="file" multiple accept="image/*,application/pdf,.dwg" capture="environment" onChange={(e) => setDil({ ...dil, arquivos: [...e.target.files] })} className="mt-1 block text-sm" />
                            {dil.arquivos.length > 0 && <p className="text-xs text-gray-500 mt-1">{dil.arquivos.length} arquivo(s)</p>}
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <Button size="sm" variant="secondary" icon="fas fa-location-crosshairs" onClick={pegarGps}>Usar minha localização</Button>
                            {dil.latitude && <span className="text-xs text-gray-600">{dil.latitude}, {dil.longitude}</span>}
                            {gps && <span className="text-xs text-gray-500">{gps}</span>}
                        </div>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setDil(null)}>Cancelar</Button>
                            <Button disabled={! dil.constatacao.trim()} onClick={() => {
                                const fd = new FormData();
                                ['realizada_em', 'resultado', 'constatacao', 'latitude', 'longitude'].forEach(k => dil[k] && fd.append(k, dil[k]));
                                dil.arquivos.forEach((a, i) => fd.append(`arquivos[${i}]`, a));
                                router.post(`/processos/${processoId}/diligencias`, fd, { forceFormData: true, preserveScroll: true, onSuccess: () => setDil(null) });
                            }}>Registrar</Button></div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}