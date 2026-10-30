/**
 * App offline da fiscalização (TR 4.52). Funciona sem internet: os formulários e os processos em
 * trâmite ficam guardados no aparelho, as vistorias (checklist, GPS, fotos marcadas) também, e o
 * fiscal sincroniza na hora ou depois. Enquanto não sincroniza, a vistoria pode ser editada.
 */
import '../../css/app.css';
import { createRoot } from 'react-dom/client';
import { useEffect, useMemo, useState } from 'react';
import { vistorias as banco, cache, pedirPersistencia } from './db';
import EditorFoto, { renderizar } from './EditorFoto';

const JSON_ACEITA = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const SITUACAO = {
    rascunho: ['Rascunho', 'bg-slate-100 text-slate-700'],
    pendente: ['Aguardando sincronizar', 'bg-amber-100 text-amber-800'],
    erro: ['Erro ao sincronizar', 'bg-red-100 text-red-700'],
    sincronizada: ['Sincronizada', 'bg-emerald-100 text-emerald-700'],
};
const dataHora = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = (Math.random() * 16) | 0; return (c === 'x' ? r : (r & 3) | 8).toString(16); }));
const localAgora = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };
const lerPref = (k, padrao) => { try { const v = localStorage.getItem(k); return v === null ? padrao : v === '1'; } catch { return padrao; } };
const gravarPref = (k, v) => { try { localStorage.setItem(k, v ? '1' : '0'); } catch { /* sem armazenamento */ } };

/** Uma vistoria está pronta para sincronizar? Devolve o que falta. */
function pendencias(v, form) {
    const falta = [];
    if (! form) return ['Escolha o formulário.'];
    for (const c of form.campos) if (c.obrigatorio && ! String(v.campos?.[c.chave] ?? '').trim()) falta.push(`Preencha "${c.rotulo}".`);
    const sem = form.itens.filter((i) => ! v.respostas?.[i.chave]?.resposta).length;
    if (sem) falta.push(`${sem} item(ns) do checklist sem resposta.`);
    if (v.processo_id && ! v.resultado) falta.push('Informe o resultado (a vistoria vai virar diligência do processo).');
    return falta;
}

function App() {
    const [sessao, setSessao] = useState(null);
    const [lista, setLista] = useState([]);
    const [doServidor, setDoServidor] = useState([]);
    const [online, setOnline] = useState(navigator.onLine);
    const [editando, setEditando] = useState(null);
    const [aviso, setAviso] = useState(null);
    const [sincronizando, setSincronizando] = useState(false);
    const [auto, setAuto] = useState(lerPref('fiscal.auto', true));

    const recarregar = async () => setLista((await banco.todas()).sort((a, b) => (b.realizada_em || '').localeCompare(a.realizada_em || '')));

    const baixar = async () => {
        try {
            const r = await fetch('/fiscal/api/sessao', { headers: JSON_ACEITA, credentials: 'same-origin' });
            if (r.status === 401 || r.status === 419) { setAviso({ erro: true, texto: 'Sua sessão expirou. Entre de novo para sincronizar.', login: true }); return null; }
            if (! r.ok) throw new Error();
            const s = await r.json();
            await cache.gravar('sessao', s);
            setSessao(s);
            fetch('/fiscal/api/vistorias', { headers: JSON_ACEITA }).then((x) => (x.ok ? x.json() : [])).then(setDoServidor).catch(() => {});
            return s;
        } catch {
            return null;
        }
    };

    useEffect(() => {
        pedirPersistencia();
        cache.ler('sessao').then((s) => s && setSessao((atual) => atual || s));
        recarregar();
        if (navigator.onLine) baixar();
        const on = () => setOnline(true); const off = () => setOnline(false);
        window.addEventListener('online', on); window.addEventListener('offline', off);
        return () => { window.removeEventListener('online', on); window.removeEventListener('offline', off); };
    }, []);

    const enviar = async (v, s) => {
        const fd = new FormData();
        const { fotos = [], status, erro, criado_em, atualizado_em, ...dados } = v;
        fd.append('dados', JSON.stringify({ ...dados, realizada_em: new Date(v.realizada_em).toISOString(), legendas: fotos.map((f) => f.legenda || null) }));
        fotos.forEach((f, i) => fd.append('fotos[]', f.blob, `foto-${i + 1}.jpg`));
        const r = await fetch('/fiscal/api/vistorias', { method: 'POST', body: fd, credentials: 'same-origin', headers: { ...JSON_ACEITA, 'X-CSRF-TOKEN': s.csrf } });
        if (r.ok) return { ok: true, ...(await r.json()) };
        let msg = `Erro ${r.status}`;
        try { const j = await r.json(); msg = j.message || Object.values(j.errors || {})[0]?.[0] || msg; } catch { /* resposta sem JSON */ }
        return { ok: false, status: r.status, msg };
    };

    /** Sincroniza as pendentes (ou só uma). As fotos saem do aparelho depois de enviadas. */
    const sincronizar = async (so = null) => {
        if (sincronizando) return;
        if (! navigator.onLine) { setAviso({ erro: true, texto: 'Sem internet. As vistorias ficam guardadas no aparelho até você sincronizar.' }); return; }
        setSincronizando(true);
        try {
            const s = await baixar();
            if (! s) { setAviso((a) => a?.login ? a : { erro: true, texto: 'Não foi possível falar com o servidor.' }); return; }
            const fila = (await banco.todas()).filter((v) => (so ? v.uuid === so : ['pendente', 'erro'].includes(v.status)));
            let ok = 0; let falhas = 0;
            for (const v of fila) {
                const r = await enviar(v, s);
                if (r.ok) {
                    ok++;
                    await banco.salvar({ ...v, status: 'sincronizada', erro: null, servidor_id: r.id, processo_numero: r.processo || null,
                        sincronizada_em: new Date().toISOString(), fotos: [], qtd_fotos: v.fotos?.length || 0 });
                } else {
                    falhas++;
                    if (r.status === 401 || r.status === 419) { setAviso({ erro: true, texto: 'Sua sessão expirou. Entre de novo para sincronizar.', login: true }); await banco.salvar({ ...v, status: 'erro', erro: 'Sessão expirada' }); break; }
                    await banco.salvar({ ...v, status: 'erro', erro: r.msg });
                }
            }
            await recarregar();
            if (fila.length) setAviso({ erro: falhas > 0, texto: `${ok} vistoria(s) sincronizada(s)${falhas ? `, ${falhas} com erro` : ''}.` });
            await baixar();
        } finally {
            setSincronizando(false);
        }
    };

    useEffect(() => {
        if (online && auto && lista.some((v) => v.status === 'pendente')) sincronizar();
    }, [online]);

    const nova = () => {
        if (! sessao?.formularios?.length) { setAviso({ erro: true, texto: 'Nenhum formulário disponível. Conecte-se à internet para baixar os formulários.' }); return; }
        setEditando({ uuid: uuid(), status: 'rascunho', formulario_id: sessao.formularios.length === 1 ? sessao.formularios[0].id : '',
            processo_id: null, objeto: '', local_descricao: '', latitude: null, longitude: null, precisao: null, realizada_em: localAgora(),
            resultado: '', campos: {}, respostas: {}, observacao: '', fotos: [], criado_em: new Date().toISOString() });
    };

    const fechar = async (v, acao) => {
        if (v) {
            await banco.salvar({ ...v, status: acao === 'rascunho' ? 'rascunho' : 'pendente', erro: null });
            await recarregar();
        }
        setEditando(null);
        if (acao === 'agora') await sincronizar(v.uuid);
        else if (acao === 'concluir') {
            setAviso({ texto: auto && navigator.onLine ? 'Vistoria concluída. Sincronizando…' : 'Vistoria concluída e guardada no aparelho. Sincronize quando tiver internet.' });
            if (auto && navigator.onLine) await sincronizar(v.uuid);
        }
    };

    if (editando) return <Formulario v={editando} sessao={sessao} onFechar={fechar} />;

    const pendentes = lista.filter((v) => v.status !== 'sincronizada');
    const enviadas = lista.filter((v) => v.status === 'sincronizada');
    const locais = new Set(enviadas.map((v) => v.uuid));
    const outras = doServidor.filter((v) => ! locais.has(v.uuid));
    const nPend = lista.filter((v) => ['pendente', 'erro'].includes(v.status)).length;

    return (
        <div className="mx-auto min-h-screen max-w-2xl bg-slate-50 pb-28">
            <Cabecalho sessao={sessao} online={online} />
            <main className="space-y-4 p-4">
                {aviso && (
                    <div className={`flex items-start gap-2 rounded-lg p-3 text-sm ${aviso.erro ? 'bg-red-50 text-red-800' : 'bg-cyan-50 text-cyan-900'}`}>
                        <p className="flex-1">{aviso.texto} {aviso.login && <a href="/login" className="font-semibold underline">Entrar</a>}</p>
                        <button type="button" onClick={() => setAviso(null)} aria-label="Fechar aviso">✕</button>
                    </div>
                )}
                <div className="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                    <div className="flex items-center gap-2">
                        <p className="flex-1 text-slate-600">{nPend ? <><b>{nPend}</b> vistoria(s) aguardando sincronização</> : 'Nada pendente de sincronização.'}</p>
                        <button type="button" onClick={() => sincronizar()} disabled={sincronizando || ! nPend} className="rounded-lg bg-cyan-700 px-3 py-2 font-semibold text-white disabled:opacity-40">
                            {sincronizando ? 'Sincronizando…' : 'Sincronizar'}
                        </button>
                    </div>
                    <label className="mt-2 flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" checked={auto} onChange={(e) => { setAuto(e.target.checked); gravarPref('fiscal.auto', e.target.checked); }} />
                        Sincronizar sozinho quando houver internet
                    </label>
                    <p className="mt-1 text-[11px] text-slate-400">Dados do GED atualizados em {sessao ? dataHora(sessao.atualizado_em) : '—'}
                        {online && <button type="button" onClick={baixar} className="ml-2 text-cyan-700 underline">atualizar</button>}</p>
                </div>

                <Secao titulo="No aparelho" vazio="Nenhuma vistoria em andamento.">
                    {pendentes.map((v) => <Cartao key={v.uuid} v={v} sessao={sessao} onAbrir={() => setEditando(v)} onSincronizar={() => sincronizar(v.uuid)}
                        onExcluir={async () => { if (window.confirm('Excluir esta vistoria do aparelho? Ela não foi sincronizada.')) { await banco.excluir(v.uuid); recarregar(); } }} />)}
                </Secao>
                <Secao titulo="Sincronizadas" vazio="Nenhuma vistoria sincronizada ainda.">
                    {enviadas.map((v) => <Cartao key={v.uuid} v={v} sessao={sessao} />)}
                    {outras.map((v) => (
                        <div key={v.uuid} className="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                            <div className="flex items-center gap-2"><b className="flex-1">{v.formulario}</b><Etiqueta s="sincronizada" /></div>
                            <p className="text-slate-600">{[v.objeto, v.local].filter(Boolean).join(' — ')}</p>
                            <p className="text-xs text-slate-500">{dataHora(v.realizada_em)}{v.processo ? ` · ${v.processo}` : ''} · {v.fotos} foto(s)</p>
                            <a href={v.pdf_url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs font-semibold text-cyan-700">Relatório em PDF</a>
                        </div>
                    ))}
                </Secao>
            </main>
            <div className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white p-3">
                <button type="button" onClick={nova} className="mx-auto block w-full max-w-2xl rounded-xl bg-cyan-700 py-3.5 text-base font-semibold text-white">+ Nova vistoria</button>
            </div>
        </div>
    );
}

function Cabecalho({ sessao, online, children }) {
    return (
        <header className="sticky top-0 z-10 flex items-center gap-2 bg-cyan-800 px-4 py-3 text-white">
            {children}
            <div className="flex-1">
                <p className="text-[11px] text-cyan-100">{sessao?.orgao || 'GPE Docs'}{sessao?.usuario ? ` · ${sessao.usuario.nome}` : ''}</p>
                <h1 className="font-semibold">Fiscalização</h1>
            </div>
            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${online ? 'bg-emerald-500' : 'bg-slate-500'}`}>{online ? 'Online' : 'Offline'}</span>
        </header>
    );
}

const Secao = ({ titulo, vazio, children }) => {
    const itens = [children].flat(2).filter(Boolean);
    return (
        <section>
            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{titulo}</h2>
            <div className="space-y-2">{itens.length ? itens : <p className="text-sm text-slate-400">{vazio}</p>}</div>
        </section>
    );
};

const Etiqueta = ({ s }) => <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${SITUACAO[s][1]}`}>{SITUACAO[s][0]}</span>;

function Cartao({ v, sessao, onAbrir, onSincronizar, onExcluir }) {
    const form = sessao?.formularios?.find((f) => f.id === Number(v.formulario_id));
    const proc = sessao?.processos?.find((p) => p.id === Number(v.processo_id));
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3 text-sm">
            <div className="flex items-center gap-2"><b className="flex-1">{form?.nome || 'Vistoria'}</b><Etiqueta s={v.status} /></div>
            <p className="text-slate-600">{[v.objeto, v.local_descricao].filter(Boolean).join(' — ') || 'Sem objeto ou local'}</p>
            <p className="text-xs text-slate-500">{dataHora(v.realizada_em)}{(proc || v.processo_numero) ? ` · ${proc?.numero || v.processo_numero}` : ''} · {v.fotos?.length || v.qtd_fotos || 0} foto(s){v.latitude ? ' · GPS' : ''}</p>
            {v.erro && <p className="mt-1 text-xs text-red-700">{v.erro}</p>}
            {v.status === 'sincronizada' ? (
                v.servidor_id && <a href={`/fiscal/vistorias/${v.servidor_id}/pdf`} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs font-semibold text-cyan-700">Relatório em PDF</a>
            ) : (
                <div className="mt-2 flex gap-2">
                    <button type="button" onClick={onAbrir} className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium">Editar</button>
                    {v.status !== 'rascunho' && <button type="button" onClick={onSincronizar} className="rounded-lg border border-cyan-600 px-3 py-1.5 font-medium text-cyan-700">Sincronizar</button>}
                    <button type="button" onClick={onExcluir} className="ml-auto px-2 text-xs text-red-600">Excluir</button>
                </div>
            )}
        </div>
    );
}

function Formulario({ v: inicial, sessao, onFechar }) {
    const [v, setV] = useState(inicial);
    const [gps, setGps] = useState('');
    const [foto, setFoto] = useState(null);
    const [buscaProc, setBuscaProc] = useState('');
    const [falta, setFalta] = useState([]);
    const form = sessao?.formularios?.find((f) => f.id === Number(v.formulario_id));
    const muda = (k, val) => setV((x) => ({ ...x, [k]: val }));
    const grupos = useMemo(() => {
        const g = [];
        for (const i of form?.itens || []) {
            const nome = i.grupo || '';
            (g.find((x) => x.nome === nome) || (g.push({ nome, itens: [] }), g[g.length - 1])).itens.push(i);
        }
        return g;
    }, [form]);
    const processos = (sessao?.processos || []).filter((p) => ! buscaProc || `${p.numero} ${p.assunto} ${p.endereco || ''}`.toLowerCase().includes(buscaProc.toLowerCase()));
    const proc = sessao?.processos?.find((p) => p.id === Number(v.processo_id));

    const pegarGps = () => {
        if (! navigator.geolocation) { setGps('Este aparelho não informa a localização.'); return; }
        setGps('Obtendo localização…');
        navigator.geolocation.getCurrentPosition(
            (p) => { setV((x) => ({ ...x, latitude: +p.coords.latitude.toFixed(7), longitude: +p.coords.longitude.toFixed(7), precisao: Math.round(p.coords.accuracy) })); setGps(''); },
            (e) => setGps(e.code === 1 ? 'Permita o acesso à localização para registrar o GPS.' : 'Não foi possível obter a localização. Tente ao ar livre.'),
            { enableHighAccuracy: true, timeout: 30000, maximumAge: 60000 });
    };
    useEffect(() => { if (v.latitude == null) pegarGps(); }, []);

    const textoCarimbo = () => `${new Date().toLocaleString('pt-BR')}${v.latitude != null ? ` · ${v.latitude}, ${v.longitude}` : ''}`;
    const novaFoto = (e) => {
        const arq = e.target.files?.[0];
        e.target.value = '';
        if (arq) setFoto({ id: uuid(), original: arq, marcas: [], legenda: '', carimbo: true, textoCarimbo: textoCarimbo(), nova: true });
    };
    const usarFoto = async (f) => {
        const blob = await renderizar(f);
        const { nova, ...resto } = f;
        setV((x) => ({ ...x, fotos: x.fotos.some((y) => y.id === f.id) ? x.fotos.map((y) => (y.id === f.id ? { ...resto, blob } : y)) : [...x.fotos, { ...resto, blob }] }));
        setFoto(null);
    };

    const concluir = (acao) => {
        const f = pendencias(v, form);
        setFalta(f);
        if (f.length) { window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
        onFechar(v, acao);
    };

    if (foto) return <EditorFoto foto={foto} onSalvar={usarFoto} onCancelar={() => setFoto(null)} />;

    const rotulo = 'mb-1 block text-xs font-semibold text-slate-600';
    const campo = 'w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm';

    return (
        <div className="mx-auto min-h-screen max-w-2xl bg-slate-50 pb-40">
            <Cabecalho sessao={sessao} online={navigator.onLine}>
                <button type="button" onClick={() => { if (window.confirm('Sair sem salvar as alterações?')) onFechar(null); }} className="text-xl" aria-label="Voltar">‹</button>
            </Cabecalho>
            <main className="space-y-4 p-4">
                {falta.length > 0 && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-800"><p className="font-semibold">Para concluir falta:</p><ul className="ml-4 list-disc">{falta.map((f) => <li key={f}>{f}</li>)}</ul></div>}

                <Bloco titulo="Vistoria">
                    <label className={rotulo}>Formulário</label>
                    <select value={v.formulario_id} onChange={(e) => muda('formulario_id', e.target.value)} className={campo}>
                        <option value="">Escolha…</option>
                        {(sessao?.formularios || []).map((f) => <option key={f.id} value={f.id}>{f.nome}</option>)}
                    </select>
                    {form?.descricao && <p className="mt-1 text-xs text-slate-500">{form.descricao}</p>}
                    <label className={`${rotulo} mt-3`}>Data e hora</label>
                    <input type="datetime-local" value={v.realizada_em} onChange={(e) => muda('realizada_em', e.target.value)} className={campo} />
                    <label className={`${rotulo} mt-3`}>Processo em trâmite (opcional)</label>
                    {proc ? (
                        <div className="flex items-center gap-2 rounded-lg bg-cyan-50 p-2 text-sm"><p className="flex-1"><b>{proc.numero}</b> — {proc.assunto}</p>
                            <button type="button" onClick={() => muda('processo_id', null)} className="text-xs text-red-600">Remover</button></div>
                    ) : (
                        <>
                            <input value={buscaProc} onChange={(e) => setBuscaProc(e.target.value)} placeholder="Buscar por número, assunto ou endereço" className={campo} />
                            {buscaProc && (
                                <ul className="mt-1 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white text-sm">
                                    {processos.slice(0, 30).map((p) => <li key={p.id}><button type="button" onClick={() => { setV((x) => ({ ...x, processo_id: p.id, local_descricao: x.local_descricao || p.endereco || '' })); setBuscaProc(''); }} className="w-full px-3 py-2 text-left hover:bg-slate-50"><b>{p.numero}</b> — {p.assunto}{p.endereco && <span className="block text-xs text-slate-500">{p.endereco}</span>}</button></li>)}
                                    {! processos.length && <li className="px-3 py-2 text-slate-400">Nenhum processo encontrado.</li>}
                                </ul>
                            )}
                            <p className="mt-1 text-[11px] text-slate-500">Vinculada a um processo, a vistoria vira diligência dele, com as fotos e o relatório.</p>
                        </>
                    )}
                </Bloco>

                <Bloco titulo="Objeto e local">
                    <label className={rotulo}>Objeto fiscalizado</label>
                    {form?.objetos?.length ? (
                        <select value={v.objeto} onChange={(e) => muda('objeto', e.target.value)} className={campo}>
                            <option value="">Escolha…</option>
                            {form.objetos.map((o) => <option key={o}>{o}</option>)}
                        </select>
                    ) : <input value={v.objeto} onChange={(e) => muda('objeto', e.target.value)} maxLength={250} placeholder="Ex.: obra na Rua X, comércio Y" className={campo} />}
                    <label className={`${rotulo} mt-3`}>Descrição do local</label>
                    <textarea value={v.local_descricao} onChange={(e) => muda('local_descricao', e.target.value)} maxLength={500} rows={2} placeholder="Endereço, ponto de referência" className={campo} />
                    <div className="mt-2 flex items-center gap-2 text-sm">
                        <p className="flex-1 text-slate-600">{v.latitude != null ? <>📍 {v.latitude}, {v.longitude} <span className="text-xs text-slate-400">(±{v.precisao} m)</span></> : (gps || 'Sem localização')}</p>
                        <button type="button" onClick={pegarGps} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium">{v.latitude != null ? 'Atualizar GPS' : 'Pegar GPS'}</button>
                    </div>
                </Bloco>

                {form?.campos?.length > 0 && (
                    <Bloco titulo="Dados">
                        {form.campos.map((c) => {
                            const val = v.campos?.[c.chave] ?? '';
                            const set = (x) => setV((y) => ({ ...y, campos: { ...y.campos, [c.chave]: x } }));
                            return (
                                <div key={c.chave} className="mb-3">
                                    <label className={rotulo}>{c.rotulo}{c.obrigatorio && <span className="text-red-600"> *</span>}</label>
                                    {c.tipo === 'texto_longo' ? <textarea value={val} onChange={(e) => set(e.target.value)} rows={3} className={campo} />
                                        : c.tipo === 'lista' ? <select value={val} onChange={(e) => set(e.target.value)} className={campo}><option value="">Escolha…</option>{c.opcoes.map((o) => <option key={o}>{o}</option>)}</select>
                                        : c.tipo === 'sim_nao' ? <div className="flex gap-2">{['Sim', 'Não'].map((o) => <button key={o} type="button" onClick={() => set(o)} className={`flex-1 rounded-lg border py-2 text-sm ${val === o ? 'border-cyan-600 bg-cyan-50 font-semibold text-cyan-800' : 'border-slate-300'}`}>{o}</button>)}</div>
                                        : <input type={c.tipo === 'numero' ? 'number' : c.tipo === 'data' ? 'date' : 'text'} inputMode={c.tipo === 'numero' ? 'decimal' : undefined} value={val} onChange={(e) => set(e.target.value)} className={campo} />}
                                </div>
                            );
                        })}
                    </Bloco>
                )}

                {grupos.map((g) => (
                    <Bloco key={g.nome || '_'} titulo={g.nome || 'Checklist'}>
                        {g.itens.map((i) => {
                            const r = v.respostas?.[i.chave] || {};
                            const set = (k, x) => setV((y) => ({ ...y, respostas: { ...y.respostas, [i.chave]: { ...y.respostas?.[i.chave], [k]: x } } }));
                            return (
                                <div key={i.chave} className="border-b border-slate-100 py-3 last:border-0">
                                    <p className="mb-2 text-sm text-slate-800">{i.texto}</p>
                                    <div className="grid grid-cols-3 gap-1.5">
                                        {Object.entries(sessao.respostas).map(([k, rot]) => (
                                            <button key={k} type="button" onClick={() => set('resposta', k)}
                                                className={`rounded-lg border py-2 text-xs font-semibold ${r.resposta === k ? (k === 'atende' ? 'border-emerald-600 bg-emerald-600 text-white' : k === 'nao_atende' ? 'border-red-600 bg-red-600 text-white' : 'border-slate-500 bg-slate-500 text-white') : 'border-slate-300 text-slate-600'}`}>{rot}</button>
                                        ))}
                                    </div>
                                    {r.resposta === 'nao_atende' && <input value={r.obs || ''} onChange={(e) => set('obs', e.target.value)} placeholder="O que foi constatado (opcional)" className={`${campo} mt-2`} />}
                                </div>
                            );
                        })}
                    </Bloco>
                ))}

                <Bloco titulo="Fotos">
                    <div className="grid grid-cols-3 gap-2">
                        {v.fotos.map((f) => <Miniatura key={f.id} f={f} onEditar={() => setFoto(f)} onRemover={() => setV((x) => ({ ...x, fotos: x.fotos.filter((y) => y.id !== f.id) }))} />)}
                        <label className="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 text-xs text-slate-500">
                            <span className="text-2xl">📷</span>Tirar foto
                            <input type="file" accept="image/*" capture="environment" onChange={novaFoto} className="hidden" />
                        </label>
                    </div>
                    <p className="mt-2 text-[11px] text-slate-500">Toque na foto para marcar (setas, círculos, texto) e escrever a legenda.</p>
                </Bloco>

                <Bloco titulo="Conclusão">
                    <label className={rotulo}>Resultado{v.processo_id && <span className="text-red-600"> *</span>}</label>
                    <select value={v.resultado} onChange={(e) => muda('resultado', e.target.value)} className={campo}>
                        <option value="">{v.processo_id ? 'Escolha…' : 'Não informado'}</option>
                        {Object.entries(sessao?.resultados || {}).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                    </select>
                    <label className={`${rotulo} mt-3`}>Observação final</label>
                    <textarea value={v.observacao} onChange={(e) => muda('observacao', e.target.value)} rows={4} className={campo} />
                </Bloco>
            </main>
            <div className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white p-3">
                <div className="mx-auto grid max-w-2xl grid-cols-2 gap-2">
                    <button type="button" onClick={() => onFechar(v, 'rascunho')} className="rounded-xl border border-slate-300 py-3 text-sm font-semibold">Salvar rascunho</button>
                    <button type="button" onClick={() => concluir('concluir')} className="rounded-xl bg-cyan-700 py-3 text-sm font-semibold text-white">Concluir</button>
                    {navigator.onLine && <button type="button" onClick={() => concluir('agora')} className="col-span-2 rounded-xl border border-cyan-700 py-2.5 text-sm font-semibold text-cyan-800">Concluir e sincronizar agora</button>}
                </div>
            </div>
        </div>
    );
}

const Bloco = ({ titulo, children }) => (
    <section className="rounded-xl border border-slate-200 bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold text-slate-800">{titulo}</h2>
        {children}
    </section>
);

function Miniatura({ f, onEditar, onRemover }) {
    const [url, setUrl] = useState('');
    useEffect(() => { const u = URL.createObjectURL(f.blob); setUrl(u); return () => URL.revokeObjectURL(u); }, [f.blob]);
    return (
        <div className="relative">
            <button type="button" onClick={onEditar} className="block aspect-square w-full overflow-hidden rounded-lg bg-slate-200"><img src={url} alt={f.legenda || 'Foto'} className="h-full w-full object-cover" /></button>
            {f.legenda && <p className="truncate text-[10px] text-slate-600">{f.legenda}</p>}
            <button type="button" onClick={onRemover} aria-label="Remover foto" className="absolute right-1 top-1 h-6 w-6 rounded-full bg-black/60 text-xs text-white">✕</button>
        </div>
    );
}

createRoot(document.getElementById('fiscal')).render(<App />);

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/fiscal/sw', { scope: '/fiscal' }).then(async () => {
        const reg = await navigator.serviceWorker.ready;
        // os arquivos desta primeira carga não passaram pelo service worker: pede para guardá-los
        const urls = [location.pathname, ...performance.getEntriesByType('resource').map((e) => e.name)
            .filter((u) => /\/build\/|cdnjs\.cloudflare\.com|fonts\.bunny\.net/.test(u))];
        reg.active?.postMessage({ tipo: 'guardar', urls });
    }).catch(() => {});
}
