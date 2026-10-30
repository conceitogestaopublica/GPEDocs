/**
 * Revisão de PDF/planta (TR 4.48.16–4.48.18, 4.48.39, 4.51.5): anotações (texto, retângulo,
 * elipse, linha, seta, caneta, marca-texto, selos), medição de distância e de área calibrada
 * pela escala da planta (precisão de 0 a 4 casas), rascunho salvo e conclusão aceito/recusado,
 * que gera um novo anexo com as anotações e preserva o original.
 *
 * Coordenadas guardadas em frações da página (0..1, origem no canto superior esquerdo).
 */
import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import * as pdfjs from 'pdfjs-dist';
import PdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';
import AdminLayout from '../../../Layouts/AdminLayout';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

let worker = null;
const garantirWorker = () => { if (! worker) { worker = new PdfWorker(); pdfjs.GlobalWorkerOptions.workerPort = worker; } };

const FERRAMENTAS = [
    ['selecionar', 'fa-arrow-pointer', 'Selecionar e mover'], ['texto', 'fa-font', 'Texto'], ['retangulo', 'fa-square', 'Retângulo'],
    ['elipse', 'fa-circle', 'Elipse'], ['linha', 'fa-minus', 'Linha'], ['seta', 'fa-arrow-right-long', 'Seta'], ['caneta', 'fa-pen', 'Caneta'],
    ['marca', 'fa-highlighter', 'Marca-texto'], ['selo', 'fa-stamp', 'Selo'], ['calibrar', 'fa-ruler-combined', 'Calibrar escala'],
    ['medida', 'fa-ruler', 'Medir distância'], ['area', 'fa-vector-square', 'Medir área'],
];
const CORES = ['#dc2626', '#2563eb', '#16a34a', '#ca8a04', '#7c3aed', '#111827'];
const SELOS = { aprovado: ['APROVADO', '#16803d'], reprovado: ['REPROVADO', '#c81e1e'], revisar: ['REVISAR', '#ca7800'] };
const RETANGULARES = ['retangulo', 'elipse', 'marca', 'area'];
const DE_DOIS_PONTOS = ['linha', 'seta', 'medida', 'calibrar'];
let seq = 0;
const novoId = () => `a${Date.now().toString(36)}${(seq++).toString(36)}`;

const formatar = (v, casas, area) => `${v.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })} ${area ? 'm²' : 'm'}`;

export default function RevisarAnexo({ anexo, processo, rascunho = null, revisoes = [], pode, usuario }) {
    const canvasRef = useRef(null);
    const caixaRef = useRef(null);
    const [doc, setDoc] = useState(null);
    const [erro, setErro] = useState(false);
    const [paginas, setPaginas] = useState([]);           // [{wmm, hmm}]
    const [pagina, setPagina] = useState(1);
    const [zoom, setZoom] = useState(1);
    const [tam, setTam] = useState({ w: 0, h: 0 });        // px na tela
    const [anot, setAnot] = useState(rascunho?.anotacoes || []);
    const [escala, setEscala] = useState(rascunho?.escala || {});
    const [precisao, setPrecisao] = useState(rascunho?.precisao ?? 2);
    const [ferramenta, setFerramenta] = useState('selecionar');
    const [cor, setCor] = useState(CORES[0]);
    const [espessura, setEspessura] = useState(0.6);
    const [selo, setSelo] = useState('aprovado');
    const [selecionado, setSelecionado] = useState(null);
    const [desenho, setDesenho] = useState(null);
    const [arrasto, setArrasto] = useState(null);
    const [concluir, setConcluir] = useState(null);
    const [sujo, setSujo] = useState(false);

    useEffect(() => {
        garantirWorker();
        pdfjs.getDocument({ url: anexo.url, withCredentials: true }).promise.then(async (d) => {
            const lista = [];
            for (let i = 1; i <= d.numPages; i++) {
                const vp = (await d.getPage(i)).getViewport({ scale: 1 });
                lista.push({ wmm: vp.width * 25.4 / 72, hmm: vp.height * 25.4 / 72 });
            }
            setPaginas(lista);
            setDoc(d);
        }).catch(() => setErro(true));
    }, [anexo.url]);

    useEffect(() => {
        if (! doc || ! caixaRef.current) return;
        let cancelado = false;
        doc.getPage(pagina).then((pg) => {
            const largura = Math.min(caixaRef.current.clientWidth - 8, 1600) * zoom;
            const base = pg.getViewport({ scale: 1 });
            const vp = pg.getViewport({ scale: largura / base.width });
            const c = canvasRef.current;
            const r = window.devicePixelRatio || 1;
            c.width = vp.width * r; c.height = vp.height * r;
            c.style.width = `${vp.width}px`; c.style.height = `${vp.height}px`;
            const ctx = c.getContext('2d');
            ctx.setTransform(r, 0, 0, r, 0, 0);
            pg.render({ canvasContext: ctx, viewport: vp }).promise.then(() => { if (! cancelado) setTam({ w: vp.width, h: vp.height }); });
        });
        return () => { cancelado = true; };
    }, [doc, pagina, zoom]);

    useEffect(() => {
        const tecla = (e) => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && selecionado && ! ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                setAnot((l) => l.filter((a) => a.id !== selecionado)); setSelecionado(null); setSujo(true);
            }
        };
        window.addEventListener('keydown', tecla);
        return () => window.removeEventListener('keydown', tecla);
    }, [selecionado]);

    const pg = paginas[pagina - 1] || { wmm: 210, hmm: 297 };
    const pxPorMm = tam.w / pg.wmm || 1;
    const metrosPorMm = Number(escala[pagina] ?? escala[1] ?? 0);
    const ponto = (e) => { const r = e.currentTarget.getBoundingClientRect(); return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))]; };
    const mmEntre = ([x0, y0], [x1, y1]) => Math.hypot((x1 - x0) * pg.wmm, (y1 - y0) * pg.hmm);
    const adicionar = (a) => { setAnot((l) => [...l, { id: novoId(), pagina, cor, espessura, ...a }]); setSujo(true); };
    const rotuloMedida = (a) => (metrosPorMm > 0 ? formatar(a.tipo === 'area' ? a.mm2 * metrosPorMm * metrosPorMm : a.mm * metrosPorMm, precisao, a.tipo === 'area') : 'calibre a escala');

    const aoDescer = (e) => {
        if (! pode || ! tam.w) return;
        const p = ponto(e);
        if (ferramenta === 'selecionar') { setSelecionado(null); return; }
        if (ferramenta === 'texto') {
            const texto = window.prompt('Texto da anotação:');
            if (texto?.trim()) adicionar({ tipo: 'texto', x: p[0], y: p[1], w: 0.25, texto: texto.trim(), tamanho: 9 });
            return;
        }
        if (ferramenta === 'selo') {
            const agora = new Date().toLocaleDateString('pt-BR');
            adicionar({ tipo: 'selo', selo, x: p[0], y: p[1], w: 42 / pg.wmm, h: 13 / pg.hmm, texto: `${usuario} — ${agora}` });
            return;
        }
        setDesenho({ tipo: ferramenta, inicio: p, pontos: [p, p] });
    };

    const aoMover = (e) => {
        if (arrasto) {
            const p = ponto(e);
            const dx = p[0] - arrasto.de[0], dy = p[1] - arrasto.de[1];
            setAnot((l) => l.map((a) => (a.id !== arrasto.id ? a : {
                ...a, ...(a.x != null ? { x: arrasto.orig.x + dx, y: arrasto.orig.y + dy } : {}),
                ...(a.pontos ? { pontos: arrasto.orig.pontos.map(([x, y]) => [x + dx, y + dy]) } : {}),
            })));
            setSujo(true);
            return;
        }
        if (! desenho) return;
        const p = ponto(e);
        setDesenho((d) => (d.tipo === 'caneta' ? { ...d, pontos: [...d.pontos, p] } : { ...d, pontos: [d.inicio, p] }));
    };

    const aoSubir = () => {
        if (arrasto) { setArrasto(null); return; }
        if (! desenho) return;
        const [a, b] = [desenho.pontos[0], desenho.pontos[desenho.pontos.length - 1]];
        const pequeno = Math.abs(b[0] - a[0]) < 0.004 && Math.abs(b[1] - a[1]) < 0.004;
        setDesenho(null);
        if (pequeno && desenho.tipo !== 'caneta') return;
        if (RETANGULARES.includes(desenho.tipo)) {
            const r = { x: Math.min(a[0], b[0]), y: Math.min(a[1], b[1]), w: Math.abs(b[0] - a[0]), h: Math.abs(b[1] - a[1]) };
            adicionar({ tipo: desenho.tipo, ...r, ...(desenho.tipo === 'area' ? { mm2: r.w * pg.wmm * r.h * pg.hmm } : {}) });
        } else if (desenho.tipo === 'calibrar') {
            const mm = mmEntre(a, b);
            const real = parseFloat(String(window.prompt(`Comprimento real desta linha (em metros). No papel: ${mm.toFixed(1)} mm.`) || '').replace(',', '.'));
            if (real > 0) { setEscala((s) => ({ ...s, [pagina]: real / mm })); setSujo(true); }
        } else if (DE_DOIS_PONTOS.includes(desenho.tipo)) {
            adicionar({ tipo: desenho.tipo, pontos: [a, b], ...(desenho.tipo === 'medida' ? { mm: mmEntre(a, b) } : {}) });
        } else if (desenho.tipo === 'caneta' && desenho.pontos.length > 1) {
            adicionar({ tipo: 'caneta', pontos: desenho.pontos.filter((_, i) => i % 2 === 0 || i === desenho.pontos.length - 1) });
        }
    };

    const pegar = (e, a) => {
        if (ferramenta !== 'selecionar' || ! pode) return;
        e.stopPropagation();
        setSelecionado(a.id);
        const svg = e.currentTarget.ownerSVGElement;
        const r = svg.getBoundingClientRect();
        setArrasto({ id: a.id, de: [(e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height], orig: { x: a.x, y: a.y, pontos: a.pontos } });
    };

    const forma = (a, temporario = false) => {
        const W = tam.w, H = tam.h;
        const sw = Math.max(1, (a.espessura ?? espessura) * pxPorMm);
        const c = a.cor ?? cor;
        const sel = a.id && a.id === selecionado;
        const comum = { stroke: c, strokeWidth: sw, fill: 'none', strokeLinecap: 'round', strokeLinejoin: 'round', style: { cursor: ferramenta === 'selecionar' ? 'move' : 'crosshair' },
            onMouseDown: temporario ? undefined : (e) => pegar(e, a) };
        const contorno = sel ? { filter: 'drop-shadow(0 0 2px #0ea5e9)' } : {};
        const pts = (a.pontos || []).map(([x, y]) => `${x * W},${y * H}`).join(' ');
        switch (a.tipo) {
            case 'retangulo': case 'area': {
                const r = temporario ? retDe(a) : a;
                return (
                    <g key={a.id || 'tmp'} style={contorno}>
                        <rect {...comum} x={r.x * W} y={r.y * H} width={r.w * W} height={r.h * H} strokeDasharray={a.tipo === 'area' ? '6 3' : undefined} />
                        {a.tipo === 'area' && ! temporario && rotulo(r.x * W + (r.w * W) / 2, r.y * H + (r.h * H) / 2, rotuloMedida(a), c)}
                    </g>
                );
            }
            case 'elipse': { const r = temporario ? retDe(a) : a; return <ellipse key={a.id || 'tmp'} {...comum} style={{ ...comum.style, ...contorno }} cx={(r.x + r.w / 2) * W} cy={(r.y + r.h / 2) * H} rx={(r.w / 2) * W} ry={(r.h / 2) * H} />; }
            case 'marca': { const r = temporario ? retDe(a) : a; return <rect key={a.id || 'tmp'} {...comum} style={{ ...comum.style, ...contorno }} stroke="none" fill={c} fillOpacity={0.3} x={r.x * W} y={r.y * H} width={r.w * W} height={r.h * H} />; }
            case 'linha': case 'calibrar': case 'caneta':
                return <polyline key={a.id || 'tmp'} {...comum} style={{ ...comum.style, ...contorno }} points={pts} strokeDasharray={a.tipo === 'calibrar' ? '4 3' : undefined} />;
            case 'seta': return <polyline key={a.id || 'tmp'} {...comum} style={{ ...comum.style, ...contorno }} points={pts} markerEnd={`url(#seta-${c.slice(1)})`} />;
            case 'medida': {
                const [[x0, y0], [x1, y1]] = a.pontos;
                const val = temporario ? (metrosPorMm > 0 ? formatar(mmEntre(a.pontos[0], a.pontos[1]) * metrosPorMm, precisao, false) : `${mmEntre(a.pontos[0], a.pontos[1]).toFixed(1)} mm`) : rotuloMedida(a);
                return (
                    <g key={a.id || 'tmp'} style={contorno}>
                        <polyline {...comum} points={pts} markerStart={`url(#traco-${c.slice(1)})`} markerEnd={`url(#traco-${c.slice(1)})`} />
                        {rotulo(((x0 + x1) / 2) * W, ((y0 + y1) / 2) * H - 10, val, c)}
                    </g>
                );
            }
            case 'texto':
                return (
                    <foreignObject key={a.id} x={a.x * W} y={a.y * H} width={(a.w || 0.25) * W} height={200} onMouseDown={(e) => pegar(e, a)} style={{ cursor: 'move', overflow: 'visible', ...contorno }}>
                        <div style={{ display: 'inline-block', background: '#ffffdc', border: `1px solid ${c}`, color: c, fontSize: `${(a.tamanho || 9) * pxPorMm * 0.35}px`, padding: '2px 4px', whiteSpace: 'pre-wrap', maxWidth: '100%' }}>{a.texto}</div>
                    </foreignObject>
                );
            case 'selo': {
                const [txt, sc] = SELOS[a.selo] || SELOS.aprovado;
                return (
                    <g key={a.id} onMouseDown={(e) => pegar(e, a)} style={{ cursor: 'move', ...contorno }}>
                        <rect x={a.x * W} y={a.y * H} width={a.w * W} height={a.h * H} rx={6} fill="white" fillOpacity={0.85} stroke={sc} strokeWidth={2} />
                        <text x={(a.x + a.w / 2) * W} y={(a.y + a.h * 0.5) * H} textAnchor="middle" fill={sc} fontWeight="bold" fontSize={a.h * H * 0.38}>{txt}</text>
                        <text x={(a.x + a.w / 2) * W} y={(a.y + a.h * 0.85) * H} textAnchor="middle" fill={sc} fontSize={a.h * H * 0.18}>{a.texto}</text>
                    </g>
                );
            }
            default: return null;
        }
    };
    const retDe = (d) => { const [a, b] = [d.pontos[0], d.pontos[d.pontos.length - 1]]; return { x: Math.min(a[0], b[0]), y: Math.min(a[1], b[1]), w: Math.abs(b[0] - a[0]), h: Math.abs(b[1] - a[1]) }; };
    const rotulo = (cx, cy, texto, c) => (
        <g pointerEvents="none">
            <rect x={cx - texto.length * 3.6 - 4} y={cy - 9} width={texto.length * 7.2 + 8} height={16} rx={3} fill="white" stroke={c} />
            <text x={cx} y={cy + 3} textAnchor="middle" fontSize={11} fontWeight="bold" fill={c}>{texto}</text>
        </g>
    );

    const salvar = () => router.put(`/processos/anexos/${anexo.id}/revisao`, { anotacoes: anot, escala, precisao }, { preserveScroll: true, onSuccess: () => setSujo(false) });
    const daPagina = anot.filter((a) => a.pagina === pagina);
    const escalaTexto = metrosPorMm > 0 ? `1 mm = ${metrosPorMm.toLocaleString('pt-BR', { maximumFractionDigits: 4 })} m (≈ 1:${Math.round(metrosPorMm * 1000).toLocaleString('pt-BR')})` : 'sem escala nesta página';

    return (
        <AdminLayout>
            <Head title={`Revisar ${anexo.nome}`} />
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Link href={`/processos/${processo.id}`} className="text-cyan-700 hover:underline text-sm"><i className="fas fa-arrow-left mr-1" />{processo.numero}</Link>
                <h1 className="text-lg font-semibold text-gray-800 truncate max-w-xl" title={anexo.nome}>Revisar: {anexo.nome}</h1>
                {! pode && <span className="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-0.5">somente leitura — o processo não está com você</span>}
                <span className="ml-auto flex gap-2">
                    {pode && <Button variant="secondary" icon="fas fa-save" onClick={salvar} disabled={! sujo}>Salvar rascunho</Button>}
                    {pode && <Button icon="fas fa-check" onClick={() => setConcluir({ resultado: 'aceito', motivo: '', enviar_email: true })}>Aceitar</Button>}
                    {pode && <Button variant="danger" icon="fas fa-xmark" onClick={() => setConcluir({ resultado: 'recusado', motivo: '', enviar_email: true })}>Recusar</Button>}
                </span>
            </div>

            {pode && (
                <div className="sticky top-16 z-20 mb-3 flex flex-wrap items-center gap-1 rounded-xl border border-gray-200 bg-white p-2 shadow-sm">
                    {FERRAMENTAS.map(([k, ic, r]) => (
                        <button key={k} type="button" title={r} onClick={() => setFerramenta(k)}
                            className={`h-9 w-9 rounded-lg text-sm ${ferramenta === k ? 'bg-cyan-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}><i className={`fas ${ic}`} /></button>
                    ))}
                    <span className="mx-1 h-6 w-px bg-gray-200" />
                    {CORES.map((c) => <button key={c} type="button" onClick={() => setCor(c)} className={`h-6 w-6 rounded-full border-2 ${cor === c ? 'border-gray-800' : 'border-white'}`} style={{ background: c }} title={c} />)}
                    <select value={espessura} onChange={(e) => setEspessura(Number(e.target.value))} className="ds-input !h-9 !w-24 text-xs" title="Espessura">
                        {[0.3, 0.6, 1, 1.6].map((v) => <option key={v} value={v}>{v} mm</option>)}
                    </select>
                    {ferramenta === 'selo' && (
                        <select value={selo} onChange={(e) => setSelo(e.target.value)} className="ds-input !h-9 !w-32 text-xs">{Object.entries(SELOS).map(([k, [t]]) => <option key={k} value={k}>{t}</option>)}</select>
                    )}
                    <span className="mx-1 h-6 w-px bg-gray-200" />
                    <label className="text-xs text-gray-600 flex items-center gap-1">Casas
                        <select value={precisao} onChange={(e) => { setPrecisao(Number(e.target.value)); setSujo(true); }} className="ds-input !h-9 !w-16 text-xs">{[0, 1, 2, 3, 4].map((v) => <option key={v} value={v}>{v}</option>)}</select>
                    </label>
                    <span className="text-[11px] text-gray-500 ml-1">Escala: {escalaTexto}</span>
                    <span className="ml-auto flex gap-1">
                        <button type="button" title="Desfazer a última" className="h-9 px-2 rounded-lg text-gray-700 hover:bg-gray-100" onClick={() => { setAnot((l) => l.slice(0, -1)); setSujo(true); }}><i className="fas fa-rotate-left" /></button>
                        <button type="button" title="Apagar selecionada (Delete)" disabled={! selecionado} className="h-9 px-2 rounded-lg text-red-600 hover:bg-red-50 disabled:opacity-30" onClick={() => { setAnot((l) => l.filter((a) => a.id !== selecionado)); setSelecionado(null); setSujo(true); }}><i className="fas fa-trash" /></button>
                    </span>
                </div>
            )}

            <div className="mb-2 flex items-center gap-2 text-sm">
                <Button size="sm" variant="secondary" icon="fas fa-chevron-left" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)} />
                <span>Página {pagina} de {paginas.length || '…'}</span>
                <Button size="sm" variant="secondary" icon="fas fa-chevron-right" disabled={pagina >= paginas.length} onClick={() => setPagina(pagina + 1)} />
                <span className="mx-2 h-5 w-px bg-gray-200" />
                <Button size="sm" variant="secondary" icon="fas fa-magnifying-glass-minus" onClick={() => setZoom((z) => Math.max(0.5, z - 0.25))} />
                <span className="text-xs text-gray-500">{Math.round(zoom * 100)}%</span>
                <Button size="sm" variant="secondary" icon="fas fa-magnifying-glass-plus" onClick={() => setZoom((z) => Math.min(4, z + 0.25))} />
                <span className="ml-3 text-xs text-gray-500">{anot.length} anotação(ões) · {daPagina.length} nesta página</span>
            </div>

            <div ref={caixaRef} className="overflow-auto rounded-xl border border-gray-200 bg-gray-100 p-1" style={{ maxHeight: '75vh' }}>
                {erro && <p className="p-6 text-sm text-red-600">Não foi possível abrir o PDF.</p>}
                <div className="relative mx-auto" style={{ width: tam.w || 'auto', height: tam.h || 'auto' }}>
                    <canvas ref={canvasRef} className="block bg-white shadow" />
                    {tam.w > 0 && (
                        <svg className="absolute inset-0" width={tam.w} height={tam.h} onMouseDown={aoDescer} onMouseMove={aoMover} onMouseUp={aoSubir} onMouseLeave={aoSubir}
                            style={{ cursor: ferramenta === 'selecionar' ? 'default' : 'crosshair' }}>
                            <defs>
                                {CORES.map((c) => (
                                    <g key={c}>
                                        <marker id={`seta-${c.slice(1)}`} markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L8,4 L0,8 z" fill={c} /></marker>
                                        <marker id={`traco-${c.slice(1)}`} markerWidth="2" markerHeight="10" refX="1" refY="5" orient="auto" markerUnits="strokeWidth"><path d="M1,0 L1,10" stroke={c} strokeWidth="2" /></marker>
                                    </g>
                                ))}
                            </defs>
                            {daPagina.map((a) => forma(a))}
                            {desenho && forma({ ...desenho, cor, espessura }, true)}
                        </svg>
                    )}
                </div>
            </div>

            {revisoes.length > 0 && (
                <div className="mt-4 rounded-xl border border-gray-200 bg-white p-4">
                    <h3 className="text-sm font-semibold text-gray-700 mb-2">Revisões concluídas deste arquivo</h3>
                    <ul className="text-sm space-y-1">
                        {revisoes.map((r, i) => (
                            <li key={i}><span className={r.resultado === 'aceito' ? 'text-emerald-700' : 'text-red-600'}>{r.resultado}</span> por {r.name} em {new Date(r.concluida_em).toLocaleString('pt-BR')}
                                {r.motivo ? ` — ${r.motivo}` : ''}{r.anexo_gerado_id && <a href={`/processos/anexos/${r.anexo_gerado_id}/download?inline=1`} target="_blank" rel="noreferrer" className="ml-2 text-cyan-700 hover:underline">ver revisado</a>}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Modal show={!! concluir} onClose={() => setConcluir(null)} title={concluir?.resultado === 'aceito' ? 'Aceitar o documento' : 'Recusar o documento'}>
                {concluir && (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">Será gerado um novo anexo com as {anot.length} anotação(ões); o arquivo original continua no processo, marcado como {concluir.resultado === 'aceito' ? 'aprovado' : 'reprovado'}.</p>
                        {concluir.resultado === 'recusado' && (
                            <>
                                <textarea value={concluir.motivo} onChange={(e) => setConcluir({ ...concluir, motivo: e.target.value })} rows={3} className="ds-input !h-auto" placeholder="Motivo da recusa" />
                                {processo.requerente_email && (
                                    <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={concluir.enviar_email} onChange={(e) => setConcluir({ ...concluir, enviar_email: e.target.checked })} />
                                        Enviar a cópia anotada para {processo.requerente_email}</label>
                                )}
                            </>
                        )}
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setConcluir(null)}>Cancelar</Button>
                            <Button variant={concluir.resultado === 'aceito' ? 'primary' : 'danger'} disabled={concluir.resultado === 'recusado' && ! concluir.motivo.trim()}
                                onClick={() => router.post(`/processos/anexos/${anexo.id}/revisao/concluir`, { ...concluir, anotacoes: anot, escala, precisao })}>Concluir</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
