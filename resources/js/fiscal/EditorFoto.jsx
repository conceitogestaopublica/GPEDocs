/**
 * Marcação da foto da vistoria (TR 4.52.13): caneta, seta, círculo e texto em cores fortes, desfazer,
 * carimbo com data/hora e coordenadas, e legenda. Não é destrutivo: guarda a original e as marcas,
 * e gera a imagem final (JPEG, até 1600 px) a cada salvamento.
 */
import { useEffect, useRef, useState } from 'react';

const MAX = 1600;
const CORES = ['#ef4444', '#facc15', '#ffffff', '#22c55e'];
const FERRAMENTAS = [['linha', 'Caneta'], ['seta', 'Seta'], ['circulo', 'Círculo'], ['texto', 'Texto']];

export async function carregarImagem(blob) {
    const url = URL.createObjectURL(blob);
    try {
        const img = new Image();
        img.src = url;
        await img.decode();
        return img;
    } finally {
        URL.revokeObjectURL(url);
    }
}

function tamanho(img) {
    const f = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
    return [Math.round(img.naturalWidth * f), Math.round(img.naturalHeight * f)];
}

function desenhar(ctx, img, marcas, carimbo, w, h) {
    ctx.drawImage(img, 0, 0, w, h);
    const esp = Math.max(4, w / 220);
    for (const m of marcas) {
        ctx.strokeStyle = m.cor; ctx.fillStyle = m.cor; ctx.lineWidth = esp; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        if (m.tipo === 'linha' && m.pontos.length > 1) {
            ctx.beginPath(); ctx.moveTo(...m.pontos[0]); m.pontos.slice(1).forEach((p) => ctx.lineTo(...p)); ctx.stroke();
        } else if (m.tipo === 'seta' && m.pontos.length > 1) {
            const [a, b] = [m.pontos[0], m.pontos[m.pontos.length - 1]];
            const ang = Math.atan2(b[1] - a[1], b[0] - a[0]); const ponta = esp * 5;
            ctx.beginPath(); ctx.moveTo(...a); ctx.lineTo(...b); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(...b);
            ctx.lineTo(b[0] - ponta * Math.cos(ang - 0.45), b[1] - ponta * Math.sin(ang - 0.45));
            ctx.lineTo(b[0] - ponta * Math.cos(ang + 0.45), b[1] - ponta * Math.sin(ang + 0.45));
            ctx.closePath(); ctx.fill();
        } else if (m.tipo === 'circulo' && m.pontos.length > 1) {
            const [a, b] = [m.pontos[0], m.pontos[m.pontos.length - 1]];
            ctx.beginPath(); ctx.ellipse((a[0] + b[0]) / 2, (a[1] + b[1]) / 2, Math.abs(b[0] - a[0]) / 2 || 1, Math.abs(b[1] - a[1]) / 2 || 1, 0, 0, Math.PI * 2); ctx.stroke();
        } else if (m.tipo === 'texto') {
            const fs = Math.max(18, w / 28);
            ctx.font = `bold ${fs}px sans-serif`; ctx.lineWidth = fs / 6; ctx.strokeStyle = '#000';
            ctx.strokeText(m.texto, ...m.pontos[0]); ctx.fillText(m.texto, ...m.pontos[0]);
        }
    }
    if (carimbo) {
        const fs = Math.max(14, w / 55);
        ctx.font = `${fs}px sans-serif`;
        const larg = ctx.measureText(carimbo).width + fs;
        ctx.fillStyle = 'rgba(0,0,0,.6)'; ctx.fillRect(w - larg, h - fs * 1.8, larg, fs * 1.8);
        ctx.fillStyle = '#fff'; ctx.fillText(carimbo, w - larg + fs / 2, h - fs * 0.6);
    }
}

/** Gera a imagem final a partir da original, das marcas e do carimbo. */
export async function renderizar(foto) {
    const img = await carregarImagem(foto.original);
    const [w, h] = tamanho(img);
    const c = document.createElement('canvas'); c.width = w; c.height = h;
    desenhar(c.getContext('2d'), img, foto.marcas || [], foto.carimbo ? foto.textoCarimbo : '', w, h);
    return new Promise((ok) => c.toBlob(ok, 'image/jpeg', 0.85));
}

export default function EditorFoto({ foto, onSalvar, onCancelar }) {
    const tela = useRef(null);
    const imagem = useRef(null);
    const atual = useRef(null);
    const [marcas, setMarcas] = useState(foto.marcas || []);
    const [ferramenta, setFerramenta] = useState('seta');
    const [cor, setCor] = useState(CORES[0]);
    const [legenda, setLegenda] = useState(foto.legenda || '');
    const [carimbo, setCarimbo] = useState(foto.carimbo ?? true);

    const redesenhar = (lista = marcas) => {
        const c = tela.current; const img = imagem.current;
        if (! c || ! img) return;
        desenhar(c.getContext('2d'), img, atual.current ? [...lista, atual.current] : lista, carimbo ? foto.textoCarimbo : '', c.width, c.height);
    };

    useEffect(() => {
        carregarImagem(foto.original).then((img) => {
            imagem.current = img;
            const [w, h] = tamanho(img);
            tela.current.width = w; tela.current.height = h;
            redesenhar();
        });
    }, []);
    useEffect(() => { redesenhar(); }, [marcas, carimbo]);

    const ponto = (e) => {
        const r = tela.current.getBoundingClientRect();
        return [(e.clientX - r.left) * (tela.current.width / r.width), (e.clientY - r.top) * (tela.current.height / r.height)];
    };
    const inicio = (e) => {
        e.preventDefault();
        const p = ponto(e);
        if (ferramenta === 'texto') {
            const t = window.prompt('Texto da marcação');
            if (t?.trim()) setMarcas((m) => [...m, { tipo: 'texto', cor, pontos: [p], texto: t.trim() }]);
            return;
        }
        tela.current.setPointerCapture(e.pointerId);
        atual.current = { tipo: ferramenta, cor, pontos: [p] };
    };
    const mover = (e) => {
        if (! atual.current) return;
        atual.current.pontos.push(ponto(e));
        redesenhar();
    };
    const fim = () => {
        if (! atual.current) return;
        const m = atual.current; atual.current = null;
        if (m.pontos.length > 1) setMarcas((l) => [...l, m]); else redesenhar();
    };

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-label="Marcar foto">
            <div className="flex flex-wrap items-center gap-1 bg-slate-900 p-2 text-sm text-white">
                {FERRAMENTAS.map(([k, r]) => (
                    <button key={k} type="button" onClick={() => setFerramenta(k)} className={`rounded px-2.5 py-1.5 ${ferramenta === k ? 'bg-cyan-600' : 'bg-slate-700'}`}>{r}</button>
                ))}
                <span className="mx-1" />
                {CORES.map((c) => (
                    <button key={c} type="button" onClick={() => setCor(c)} aria-label={`Cor ${c}`} className={`h-7 w-7 rounded-full border-2 ${cor === c ? 'border-white' : 'border-transparent'}`} style={{ background: c }} />
                ))}
                <button type="button" onClick={() => setMarcas((m) => m.slice(0, -1))} disabled={! marcas.length} className="ml-auto rounded bg-slate-700 px-2.5 py-1.5 disabled:opacity-40">Desfazer</button>
            </div>
            <div className="flex flex-1 items-center justify-center overflow-hidden p-2">
                <canvas ref={tela} onPointerDown={inicio} onPointerMove={mover} onPointerUp={fim} onPointerCancel={fim}
                    className="max-h-full max-w-full touch-none bg-slate-800" />
            </div>
            <div className="space-y-2 bg-slate-900 p-3">
                <input value={legenda} onChange={(e) => setLegenda(e.target.value)} maxLength={300} placeholder="Legenda da foto"
                    className="w-full rounded-lg border-0 px-3 py-2 text-sm" />
                <label className="flex items-center gap-2 text-sm text-white">
                    <input type="checkbox" checked={carimbo} onChange={(e) => setCarimbo(e.target.checked)} /> Carimbar data, hora e coordenadas
                </label>
                <div className="flex gap-2">
                    <button type="button" onClick={onCancelar} className="flex-1 rounded-lg bg-slate-700 py-2.5 font-semibold text-white">Cancelar</button>
                    <button type="button" onClick={() => onSalvar({ ...foto, marcas, legenda: legenda.trim(), carimbo })} className="flex-1 rounded-lg bg-cyan-600 py-2.5 font-semibold text-white">Usar foto</button>
                </div>
            </div>
        </div>
    );
}
