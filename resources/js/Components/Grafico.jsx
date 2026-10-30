/**
 * Moldura dos gráficos e tabelas do painel de indicadores (TR 4.29.2–7): título, descrição, "como ler",
 * download em PNG (o SVG do gráfico desenhado num canvas), CSV e XLSX dos dados mostrados, e tela cheia.
 * `children` recebe a altura disponível (maior em tela cheia).
 */
import { useEffect, useRef, useState } from 'react';

export const CORES = ['#0e7490', '#7c3aed', '#16a34a', '#f59e0b', '#dc2626', '#2563eb', '#db2777', '#64748b', '#0891b2', '#65a30d'];

const nomeArquivo = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\w]+/g, '-').replace(/^-|-$/g, '').toLowerCase() || 'indicador';

function baixar(blob, nome) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nome;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

export default function Grafico({ titulo, descricao, comoLer, dados = [], colunas, altura = 280, tabela = false, className = '', children }) {
    const caixa = useRef(null);
    const [info, setInfo] = useState(false);
    const [cheia, setCheia] = useState(false);
    const cols = colunas || (dados[0] ? Object.keys(dados[0]).map((k) => ({ key: k, label: k })) : []);

    useEffect(() => {
        const muda = () => setCheia(document.fullscreenElement === caixa.current);
        document.addEventListener('fullscreenchange', muda);
        return () => document.removeEventListener('fullscreenchange', muda);
    }, []);

    const csv = () => {
        const esc = (v) => { const s = v == null ? '' : String(v); return /[;"\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
        const linhas = [cols.map((c) => esc(c.label)).join(';'), ...dados.map((d) => cols.map((c) => esc(d[c.key])).join(';'))];
        baixar(new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' }), `${nomeArquivo(titulo)}.csv`);
    };

    const xlsx = async () => {
        const r = await fetch('/relatorios/indicadores/planilha', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
            body: JSON.stringify({ titulo, colunas: cols.map((c) => c.label), linhas: dados.map((d) => cols.map((c) => d[c.key] ?? null)) }),
        });
        if (r.ok) baixar(await r.blob(), `${nomeArquivo(titulo)}.xlsx`);
    };

    const png = async () => {
        const svg = caixa.current?.querySelector('.recharts-wrapper > svg');
        if (! svg) return;
        const { width, height } = svg.getBoundingClientRect();
        const copia = svg.cloneNode(true);
        copia.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        copia.setAttribute('width', width); copia.setAttribute('height', height);
        const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(copia)], { type: 'image/svg+xml;charset=utf-8' }));
        const img = new Image();
        img.onload = () => {
            const escala = 2; const topo = 36; const legenda = caixa.current.querySelector('.recharts-legend-wrapper');
            const legH = legenda ? legenda.getBoundingClientRect().height : 0;
            const c = document.createElement('canvas');
            c.width = width * escala; c.height = (height + topo + legH) * escala;
            const ctx = c.getContext('2d'); ctx.scale(escala, escala);
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, width, height + topo + legH);
            ctx.fillStyle = '#0f172a'; ctx.font = 'bold 15px Inter, Arial, sans-serif'; ctx.fillText(titulo, 8, 22);
            ctx.drawImage(img, 0, topo, width, height);
            if (legenda) { ctx.font = '12px Inter, Arial, sans-serif'; ctx.fillStyle = '#475569'; ctx.fillText([...legenda.querySelectorAll('.recharts-legend-item-text')].map((e) => e.textContent).join('     '), 8, topo + height + 14); }
            URL.revokeObjectURL(url);
            c.toBlob((b) => baixar(b, `${nomeArquivo(titulo)}.png`), 'image/png');
        };
        img.src = url;
    };

    const telaCheia = () => (document.fullscreenElement ? document.exitFullscreen() : caixa.current?.requestFullscreen?.());
    const botao = 'rounded px-1.5 py-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700';

    return (
        <section ref={caixa} className={`flex flex-col rounded-xl border border-[var(--ds-border)] bg-white p-4 ${cheia ? 'overflow-auto' : ''} ${className}`}>
            <header className="mb-2 flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <h3 className="text-sm font-semibold text-gray-800">{titulo}</h3>
                    {descricao && <p className="text-xs text-gray-500">{descricao}</p>}
                </div>
                <div className="flex shrink-0 items-center text-xs">
                    {comoLer && <button type="button" onClick={() => setInfo(! info)} className={botao} title="Como ler este gráfico" aria-label="Como ler"><i className="fas fa-circle-info" /></button>}
                    {! tabela && <button type="button" onClick={png} className={botao} title="Baixar imagem (PNG)" aria-label="Baixar PNG"><i className="fas fa-image" /></button>}
                    <button type="button" onClick={csv} className={botao} title="Baixar dados (CSV)" aria-label="Baixar CSV"><i className="fas fa-file-csv" /></button>
                    <button type="button" onClick={xlsx} className={botao} title="Baixar dados (Excel)" aria-label="Baixar XLSX"><i className="fas fa-file-excel" /></button>
                    <button type="button" onClick={telaCheia} className={botao} title="Tela cheia" aria-label="Tela cheia"><i className={`fas ${cheia ? 'fa-compress' : 'fa-expand'}`} /></button>
                </div>
            </header>
            {info && <p className="mb-2 rounded-lg bg-cyan-50 p-2 text-xs text-cyan-900">{comoLer}</p>}
            {dados.length === 0 ? <p className="py-8 text-center text-sm text-gray-400">Sem dados no período.</p>
                : <div className="min-h-0 flex-1">{children(cheia ? Math.max(altura, window.innerHeight - 140) : altura)}</div>}
        </section>
    );
}
