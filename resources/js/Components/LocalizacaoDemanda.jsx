/**
 * Onde fica a demanda (TR 4.32.21, 4.33.9): busca pelo endereço, clique no mapa para
 * marcar o pino, e o endereço/referência em texto. value = { latitude, longitude, endereco }.
 */
import { useState } from 'react';
import MapaLeaflet from './MapaLeaflet';

export default function LocalizacaoDemanda({ value = {}, onChange, cidade = '', erro = null, obrigatoria = false }) {
    const [busca, setBusca] = useState('');
    const [achados, setAchados] = useState([]);
    const [buscando, setBuscando] = useState(false);
    const v = value || {};
    const set = (novo) => onChange({ ...v, ...novo });

    const procurar = async () => {
        if (busca.trim().length < 3) return;
        setBuscando(true);
        try {
            const q = cidade ? `${busca}, ${cidade}` : busca;
            const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=br&q=${encodeURIComponent(q)}`);
            setAchados(await r.json());
        } catch { setAchados([]); }
        setBuscando(false);
    };

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <input value={busca} onChange={(e) => setBusca(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); procurar(); } }}
                    className="ds-input flex-1" placeholder="Procurar endereço (rua, número, bairro)…" />
                <button type="button" onClick={procurar} className="px-3 rounded border border-gray-200 text-sm text-gray-700 hover:bg-gray-50">{buscando ? '…' : 'Procurar'}</button>
            </div>
            {achados.length > 0 && (
                <ul className="border border-gray-200 rounded divide-y divide-gray-100 text-sm">
                    {achados.map(a => (
                        <li key={a.place_id}><button type="button" className="w-full text-left px-3 py-1.5 hover:bg-gray-50"
                            onClick={() => { set({ latitude: Number(parseFloat(a.lat).toFixed(7)), longitude: Number(parseFloat(a.lon).toFixed(7)), endereco: v.endereco || a.display_name.split(',').slice(0, 3).join(',') }); setAchados([]); }}>{a.display_name}</button></li>
                    ))}
                </ul>
            )}
            <MapaLeaflet cidade={cidade} marcado={v.latitude != null ? { lat: v.latitude, lng: v.longitude } : null}
                onMarcar={(lat, lng) => set({ latitude: lat, longitude: lng })} altura={280} />
            <p className="text-[11px] text-gray-500">
                Clique no mapa para marcar o ponto{obrigatoria ? ' (obrigatório)' : ''}.
                {v.latitude != null && <> Marcado: {v.latitude}, {v.longitude} · <button type="button" className="text-red-600 hover:underline" onClick={() => set({ latitude: null, longitude: null })}>tirar o ponto</button></>}
            </p>
            <input value={v.endereco || ''} onChange={(e) => set({ endereco: e.target.value })} maxLength={500} className="ds-input" placeholder="Endereço ou ponto de referência" />
            {erro && <p className="text-xs text-red-600">{erro}</p>}
        </div>
    );
}
