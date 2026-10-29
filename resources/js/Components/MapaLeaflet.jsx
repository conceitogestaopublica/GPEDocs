/**
 * Mapa (Leaflet + OpenStreetMap, como no mapa de inadimplência do Tributário). Mostra
 * pontos coloridos com popup ou, editável, um pino que o usuário marca clicando.
 * Sem pontos, centraliza na cidade (procura o nome no OpenStreetMap uma vez).
 */
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect, useRef } from 'react';

const escapar = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
export { escapar };

const centros = {};
async function centroDaCidade(cidade) {
    if (! cidade) return null;
    if (centros[cidade] !== undefined) return centros[cidade];
    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=br&q=${encodeURIComponent(cidade)}`);
        const [l] = await r.json();
        centros[cidade] = l ? [parseFloat(l.lat), parseFloat(l.lon)] : null;
    } catch { centros[cidade] = null; }
    return centros[cidade];
}

export default function MapaLeaflet({ pontos = [], marcado = null, onMarcar = null, cidade = '', altura = 320, className = '' }) {
    const caixa = useRef(null);
    const mapa = useRef(null);
    const camada = useRef(null);
    const ajustou = useRef(false);
    const onMarcarRef = useRef(onMarcar);
    useEffect(() => { onMarcarRef.current = onMarcar; });

    useEffect(() => {
        const m = L.map(caixa.current).setView([-15.8, -47.9], 4);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(m);
        camada.current = L.layerGroup().addTo(m);
        m.on('click', (e) => onMarcarRef.current?.(Number(e.latlng.lat.toFixed(7)), Number(e.latlng.lng.toFixed(7))));
        mapa.current = m;
        return () => { m.remove(); mapa.current = null; };
    }, []);

    useEffect(() => {
        const m = mapa.current;
        if (! m) return;
        camada.current.clearLayers();
        const todos = [...pontos];
        if (marcado?.lat != null) todos.push({ ...marcado, cor: '#0e7490', destaque: true });
        todos.forEach(p => {
            const c = L.circleMarker([p.lat, p.lng], { radius: p.destaque ? 10 : 7, color: '#fff', weight: 2, fillColor: p.cor || '#0e7490', fillOpacity: 0.9 });
            if (p.popup) c.bindPopup(p.popup);
            c.addTo(camada.current);
        });
        if (todos.length && ! ajustou.current) {
            ajustou.current = true;
            todos.length === 1 ? m.setView([todos[0].lat, todos[0].lng], 16) : m.fitBounds(L.latLngBounds(todos.map(p => [p.lat, p.lng])), { padding: [30, 30], maxZoom: 16 });
        } else if (! todos.length && ! ajustou.current) {
            centroDaCidade(cidade).then(c => { if (c && mapa.current && ! ajustou.current) mapa.current.setView(c, 14); });
        }
    }, [pontos, marcado?.lat, marcado?.lng, cidade]);

    return <div ref={caixa} style={{ height: altura }} className={`rounded border border-gray-200 z-0 ${onMarcar ? 'cursor-crosshair' : ''} ${className}`} />;
}
