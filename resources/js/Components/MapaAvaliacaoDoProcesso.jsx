/**
 * Na tela do processo: onde fica a demanda (mapa, editável por quem está com o processo),
 * avaliações por estrelas de cada rodada, registro da avaliação no balcão e reabertura
 * (TR 4.32.21, 4.32.25–27, 4.33.9, 4.33.24).
 */
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';
import MapaLeaflet from './MapaLeaflet';
import LocalizacaoDemanda from './LocalizacaoDemanda';

export function Estrelas({ nota = 0, tamanho = 'text-base', onEscolher = null }) {
    return (
        <span className={`inline-flex gap-0.5 ${tamanho}`}>
            {[1, 2, 3, 4, 5].map(n => (
                <button key={n} type="button" disabled={! onEscolher} onClick={() => onEscolher?.(n)} aria-label={`${n} estrela(s)`}
                    className={`${n <= nota ? 'text-amber-400' : 'text-gray-300'} ${onEscolher ? 'hover:scale-110 cursor-pointer' : 'cursor-default'}`}>★</button>
            ))}
        </span>
    );
}

export default function MapaAvaliacaoDoProcesso({ processoId, dados, className = '' }) {
    const [editando, setEditando] = useState(false);
    const [reabrindo, setReabrindo] = useState(false);
    const loc = dados?.localizacao || {};
    const local = useForm({ localizacao: { latitude: loc.latitude, longitude: loc.longitude, endereco: loc.endereco || '' } });
    const aval = useForm({ nota: 0, comentario: '' });
    const reab = useForm({ motivo: '' });
    if (! dados) return null;
    const temLocal = loc.latitude != null;
    const mostrarMapa = temLocal || loc.exigencia !== 'nao';

    return (
        <div className={`grid grid-cols-1 lg:grid-cols-2 gap-6 ${className}`}>
            {mostrarMapa && (
                <Card title="Localização da demanda">
                    {editando ? (
                        <form onSubmit={(e) => { e.preventDefault(); local.post(`/processos/${processoId}/localizacao`, { preserveScroll: true, onSuccess: () => setEditando(false) }); }} className="space-y-3">
                            <LocalizacaoDemanda value={local.data.localizacao} onChange={(v) => local.setData('localizacao', v)} cidade={dados.cidade}
                                erro={local.errors.localizacao} obrigatoria={loc.exigencia === 'obrigatoria'} />
                            <div className="flex gap-2">
                                <Button type="submit" variant="primary" size="sm" loading={local.processing}>Salvar localização</Button>
                                <Button type="button" variant="secondary" size="sm" onClick={() => setEditando(false)}>Cancelar</Button>
                            </div>
                        </form>
                    ) : (
                        <>
                            {temLocal ? <MapaLeaflet marcado={{ lat: loc.latitude, lng: loc.longitude }} altura={240} /> : <p className="text-sm text-gray-400">Sem local marcado.</p>}
                            {loc.endereco && <p className="mt-2 text-sm text-gray-700">{loc.endereco}</p>}
                            <div className="mt-2 flex items-center gap-3">
                                {loc.pode && <Button size="sm" variant="secondary" icon="fas fa-location-dot" onClick={() => setEditando(true)}>{temLocal ? 'Alterar local' : 'Marcar local'}</Button>}
                                {temLocal && <a href={`https://www.openstreetmap.org/?mlat=${loc.latitude}&mlon=${loc.longitude}#map=18/${loc.latitude}/${loc.longitude}`} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline">Abrir no OpenStreetMap</a>}
                            </div>
                        </>
                    )}
                </Card>
            )}

            {(dados.avaliacoes.length > 0 || dados.pode_avaliar || dados.pode_reabrir) && (
                <Card title="Avaliação do atendimento">
                    {dados.avaliacoes.length === 0 && <p className="text-sm text-gray-400 mb-3">Ainda não avaliado.</p>}
                    <ul className="divide-y divide-gray-100 mb-3">
                        {dados.avaliacoes.map(a => (
                            <li key={a.rodada} className="py-2">
                                <p className="flex items-center gap-2"><Estrelas nota={a.nota} /><span className="text-sm font-semibold text-gray-700">{a.nota}/5</span>
                                    {a.rodada > 0 && <span className="text-[10px] rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">após a {a.rodada}ª reabertura</span>}</p>
                                {a.comentario && <p className="text-sm text-gray-700 mt-1">“{a.comentario}”</p>}
                                <p className="text-xs text-gray-500">{new Date(a.created_at).toLocaleString('pt-BR')} · {a.origem === 'portal' ? 'pelo cidadão no portal' : `no balcão, registrada por ${a.registrado_por || '-'}`}</p>
                            </li>
                        ))}
                    </ul>
                    {dados.pode_avaliar && (
                        <form onSubmit={(e) => { e.preventDefault(); aval.post(`/processos/${processoId}/avaliacao`, { preserveScroll: true, onSuccess: () => aval.reset() }); }} className="space-y-2 border-t border-gray-100 pt-3">
                            <p className="text-xs font-semibold uppercase text-gray-500">Registrar a avaliação dada no balcão</p>
                            <Estrelas nota={aval.data.nota} tamanho="text-2xl" onEscolher={(n) => aval.setData('nota', n)} />
                            {aval.errors.nota && <p className="text-xs text-red-600">{aval.errors.nota}</p>}
                            <textarea value={aval.data.comentario} onChange={(e) => aval.setData('comentario', e.target.value)} rows={2} maxLength={2000} className="ds-input" placeholder="Comentário do cidadão (opcional)" />
                            <Button type="submit" size="sm" variant="primary" disabled={! aval.data.nota} loading={aval.processing}>Registrar avaliação</Button>
                        </form>
                    )}
                    {dados.pode_reabrir && (
                        <div className="border-t border-gray-100 pt-3 mt-3">
                            <Button size="sm" variant="secondary" icon="fas fa-rotate-left" onClick={() => setReabrindo(true)}>Reabrir processo</Button>
                            <p className="mt-1 text-[11px] text-gray-500">Volta para o setor que concluiu. O cidadão pode avaliar de novo depois da nova conclusão.</p>
                        </div>
                    )}
                </Card>
            )}

            <Modal show={reabrindo} onClose={() => setReabrindo(false)} title="Reabrir processo">
                <form onSubmit={(e) => { e.preventDefault(); reab.post(`/processos/${processoId}/reabrir`, { onSuccess: () => { setReabrindo(false); reab.reset(); } }); }} className="space-y-3">
                    <label className="block text-sm font-medium text-gray-700">Motivo</label>
                    <textarea value={reab.data.motivo} onChange={(e) => reab.setData('motivo', e.target.value)} rows={3} maxLength={2000} className="ds-input" />
                    {reab.errors.motivo && <p className="text-xs text-red-600">{reab.errors.motivo}</p>}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setReabrindo(false)}>Cancelar</Button>
                        <Button type="submit" variant="primary" loading={reab.processing} disabled={! reab.data.motivo.trim()}>Reabrir</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
