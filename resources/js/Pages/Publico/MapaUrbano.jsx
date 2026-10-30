/**
 * Consulta prévia de viabilidade (TR 4.50): mapa público e responsivo com base de ruas ou satélite
 * com rótulos (híbrido), zonas, camadas próprias sobrepostas, lotes clicáveis (aparecem com zoom)
 * e a ficha do lote: zona, parâmetros urbanísticos, usos, o que cabe no lote e o link de dúvida.
 */
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';

const ZOOM_LOTES = 16;
const num = (v, c = 2) => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: c });

export default function MapaUrbano({ base = '/mapa-urbano', orgao }) {
    const caixa = useRef(null);
    const mapa = useRef(null);
    const camadaLotes = useRef(null);
    const [dados, setDados] = useState(null);
    const [ficha, setFicha] = useState(null);
    const [busca, setBusca] = useState('');
    const [achados, setAchados] = useState([]);
    const [aviso, setAviso] = useState('');

    const abrirLote = async (id) => {
        const r = await fetch(`${base}/lote?id=${id}`);
        if (r.ok) setFicha(await r.json());
    };

    useEffect(() => {
        fetch(`${base}/dados`).then((r) => r.json()).then(setDados);
    }, [base]);

    useEffect(() => {
        if (! dados || ! caixa.current || mapa.current) return;
        const m = L.map(caixa.current, { zoomControl: true }).setView(dados.centro, dados.zoom);
        mapa.current = m;
        const ruas = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 20, maxNativeZoom: 19 });
        const satelite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Esri', maxZoom: 20, maxNativeZoom: 19 });
        const rotulos = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19 });
        ruas.addTo(m);
        const bases = { Ruas: ruas, 'Satélite (híbrido)': L.layerGroup([satelite, rotulos]) };

        const zonas = L.layerGroup(dados.zonas.map((z) => L.geoJSON(z.geometria, { style: { color: z.cor, weight: 2, fillOpacity: 0.12 } })
            .bindTooltip(`${z.sigla} — ${z.nome}`, { sticky: true })));
        zonas.addTo(m);
        const extras = { Zonas: zonas };
        dados.camadas.forEach((c) => {
            const lay = L.geoJSON(c.geojson, {
                style: { color: c.cor, weight: 3, fillOpacity: 0.2 },
                pointToLayer: (_, ll) => L.circleMarker(ll, { radius: 7, color: c.cor, fillColor: c.cor, fillOpacity: 0.8 }),
                onEachFeature: (f, l) => { const n = f.properties?.nome || f.properties?.name; if (n) l.bindTooltip(n); },
            });
            if (c.visivel) lay.addTo(m);
            extras[`${c.nome} (${c.tipo})`] = lay;
        });
        camadaLotes.current = L.geoJSON(null, {
            style: (f) => ({ color: f.properties.cor || '#475569', weight: 1, fillOpacity: 0.05 }),
            onEachFeature: (f, l) => { l.on('click', () => abrirLote(f.properties.id)); l.bindTooltip(`${f.properties.inscricao}${f.properties.endereco ? ` · ${f.properties.endereco}` : ''}`); },
        }).addTo(m);
        extras.Lotes = camadaLotes.current;
        L.control.layers(bases, extras, { collapsed: window.innerWidth < 768 }).addTo(m);

        const carregarLotes = async () => {
            if (m.getZoom() < ZOOM_LOTES) { camadaLotes.current.clearLayers(); setAviso(`Aproxime o mapa para ver os lotes.`); return; }
            const b = m.getBounds();
            const r = await fetch(`${base}/lotes?bbox=${[b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',')}`);
            const fc = await r.json();
            camadaLotes.current.clearLayers();
            camadaLotes.current.addData(fc);
            setAviso(fc.features.length ? '' : 'Nenhum lote cadastrado nesta área.');
        };
        m.on('moveend', carregarLotes);
        carregarLotes();
        setTimeout(() => m.invalidateSize(), 200);
    }, [dados]);

    useEffect(() => {
        if (busca.trim().length < 2) { setAchados([]); return; }
        const t = setTimeout(() => fetch(`${base}/busca?q=${encodeURIComponent(busca)}`).then((r) => r.json()).then(setAchados), 300);
        return () => clearTimeout(t);
    }, [busca, base]);

    const irPara = (a) => { setAchados([]); setBusca(a.inscricao); mapa.current?.setView(a.centro, 18); abrirLote(a.id); };
    const usos = ficha?.zona?.usos || {};

    return (
        <div className="flex h-screen flex-col bg-gray-50">
            <Head title="Consulta prévia" />
            <header className="relative z-[1100] flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-3 py-2">
                <div className="mr-auto"><p className="text-[11px] text-gray-500">{orgao}</p><h1 className="text-base font-semibold text-gray-800">Consulta prévia de viabilidade</h1></div>
                <div className="relative w-full sm:w-80">
                    <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Inscrição ou endereço" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    {achados.length > 0 && (
                        <ul className="absolute z-[1200] mt-1 max-h-72 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
                            {achados.map((a) => <li key={a.id}><button type="button" onClick={() => irPara(a)} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50"><b>{a.inscricao}</b><span className="block text-xs text-gray-500">{a.endereco}</span></button></li>)}
                        </ul>
                    )}
                </div>
            </header>
            <div className="relative flex flex-1 flex-col md:flex-row overflow-hidden">
                <div ref={caixa} className="flex-1 min-h-[50vh]" />
                {aviso && <div className="pointer-events-none absolute left-1/2 top-3 z-[500] -translate-x-1/2 rounded-full bg-white/90 px-3 py-1 text-xs text-gray-600 shadow">{aviso}</div>}
                {ficha && (
                    <aside className="max-h-[50vh] md:max-h-none w-full md:w-96 overflow-y-auto border-t md:border-t-0 md:border-l border-gray-200 bg-white p-4 text-sm">
                        <div className="flex items-start justify-between"><h2 className="font-semibold text-gray-800">Lote {ficha.lote.inscricao}</h2>
                            <button type="button" onClick={() => setFicha(null)} className="text-gray-400 hover:text-gray-700" aria-label="Fechar">✕</button></div>
                        <p className="text-gray-600">{[ficha.lote.logradouro, ficha.lote.numero].filter(Boolean).join(', ')}{ficha.lote.bairro ? ` — ${ficha.lote.bairro}` : ''}</p>
                        <p className="text-xs text-gray-500">{ficha.lote.quadra ? `Quadra ${ficha.lote.quadra} · ` : ''}{ficha.lote.lote ? `Lote ${ficha.lote.lote} · ` : ''}Área {num(ficha.lote.area)} m²</p>
                        {ficha.zona ? (
                            <>
                                <p className="mt-3 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold text-white" style={{ background: ficha.zona.cor }}>{ficha.zona.sigla} — {ficha.zona.nome}</p>
                                <table className="mt-2 w-full text-xs"><tbody>
                                    {ficha.zona.parametros.map((p) => <tr key={p.rotulo} className="border-b border-gray-100"><td className="py-1 text-gray-600">{p.rotulo}</td><td className="py-1 text-right font-semibold">{num(p.valor)}</td></tr>)}
                                </tbody></table>
                                {Object.keys(ficha.potencial).length > 0 && (
                                    <div className="mt-3 rounded-lg bg-cyan-50 p-2 text-xs">
                                        <p className="font-semibold text-cyan-900 mb-1">Neste lote</p>
                                        {ficha.potencial.area_ocupavel != null && <p>Ocupação máxima: <b>{num(ficha.potencial.area_ocupavel)} m²</b></p>}
                                        {ficha.potencial.area_construivel_basica != null && <p>Construção (CA básico): <b>{num(ficha.potencial.area_construivel_basica)} m²</b></p>}
                                        {ficha.potencial.area_construivel_maxima != null && <p>Construção (CA máximo): <b>{num(ficha.potencial.area_construivel_maxima)} m²</b></p>}
                                        {ficha.potencial.area_permeavel_minima != null && <p>Área permeável mínima: <b>{num(ficha.potencial.area_permeavel_minima)} m²</b></p>}
                                    </div>
                                )}
                                {[['permitidos', 'Usos permitidos', 'text-emerald-700'], ['permissiveis', 'Usos permissíveis (com análise)', 'text-amber-700'], ['proibidos', 'Usos proibidos', 'text-red-700']].map(([k, r, cor]) => (usos[k] || []).length > 0 && (
                                    <div key={k} className="mt-3"><p className={`text-xs font-semibold ${cor}`}>{r}</p><ul className="ml-4 list-disc text-xs text-gray-700">{usos[k].map((u) => <li key={u}>{u}</li>)}</ul></div>
                                ))}
                                {ficha.zona.observacao && <p className="mt-3 text-xs text-gray-600 whitespace-pre-wrap">{ficha.zona.observacao}</p>}
                            </>
                        ) : <p className="mt-3 text-xs text-amber-700">Lote fora das zonas cadastradas.</p>}
                        {ficha.duvida_url && <a href={ficha.duvida_url} target="_blank" rel="noreferrer" className="mt-4 block rounded-lg bg-cyan-700 py-2 text-center font-semibold text-white">Tenho uma dúvida sobre este lote</a>}
                        {dados?.aviso && <p className="mt-3 text-[11px] text-gray-500 whitespace-pre-wrap">{dados.aviso}</p>}
                    </aside>
                )}
            </div>
        </div>
    );
}
