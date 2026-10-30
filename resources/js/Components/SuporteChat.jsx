/**
 * Aba Suporte do chat flutuante (TR 4.26.1–3, 4.26.7): atendentes on-line, autoatendimento pela central
 * de ajuda (assunto → artigos → "resolveu?"), abertura do atendimento numerado, retomada pelo número e a
 * conversa com o suporte.
 */
import { useEffect, useRef, useState } from 'react';

const COR = { aberto: 'bg-amber-100 text-amber-800', em_atendimento: 'bg-cyan-100 text-cyan-800', resolvido: 'bg-emerald-100 text-emerald-700',
    fechado: 'bg-slate-100 text-slate-600', cancelado: 'bg-red-100 text-red-700' };
const hora = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');

export default function SuporteChat({ fetchJson, painel, recarregar }) {
    const [tela, setTela] = useState('inicio');
    const [assunto, setAssunto] = useState(null);
    const [artigos, setArtigos] = useState([]);
    const [aberto, setAberto] = useState(null);       // artigo expandido
    const [form, setForm] = useState({ titulo: '', descricao: '' });
    const [atual, setAtual] = useState(null);          // atendimento na conversa
    const [numero, setNumero] = useState('');
    const [erro, setErro] = useState('');
    const [aviso, setAviso] = useState('');

    const escolherAssunto = async (a) => {
        setAssunto(a);
        setArtigos(await fetchJson(`/suporte/api/artigos?assunto=${a.id}`).catch(() => []));
        setTela('artigos');
    };
    const verArtigo = (art) => {
        setAberto(aberto === art.id ? null : art.id);
        if (aberto !== art.id) fetchJson(`/suporte/api/artigos/${art.id}/visto`, { method: 'POST', body: '{}' }).catch(() => {});
    };
    const retorno = async (art, resolveu) => {
        await fetchJson(`/suporte/api/artigos/${art.id}/retorno`, { method: 'POST', body: JSON.stringify({ resolveu }) }).catch(() => {});
        if (resolveu) { setAviso('Que bom que o artigo ajudou!'); setTela('inicio'); setAssunto(null); }
        else { setForm({ titulo: art.titulo, descricao: '' }); setTela('abrir'); }
    };
    const abrir = async (e) => {
        e.preventDefault();
        setErro('');
        try {
            const r = await fetchJson('/suporte/api/atendimentos', { method: 'POST', body: JSON.stringify({ ...form, assunto_id: assunto?.id || null }) });
            setForm({ titulo: '', descricao: '' });
            setAtual(r.atendimento); setTela('conversa'); recarregar();
        } catch { setErro('Não foi possível abrir o atendimento. Confira o título e a descrição.'); }
    };
    const retomar = async (e) => {
        e.preventDefault();
        setErro('');
        try { const r = await fetchJson(`/suporte/api/buscar?numero=${encodeURIComponent(numero)}`); setAtual(r.atendimento); setTela('conversa'); setNumero(''); }
        catch { setErro('Atendimento não encontrado (ou você não participa dele).'); }
    };

    if (tela === 'conversa' && atual) return <Conversa fetchJson={fetchJson} atual={atual} setAtual={setAtual} voltar={() => { setTela('inicio'); setAtual(null); recarregar(); }} />;

    const atendentes = painel?.atendentes || [];
    return (
        <div className="flex-1 overflow-y-auto">
            {aviso && <p className="m-3 rounded-lg bg-emerald-50 p-2 text-xs text-emerald-800" onClick={() => setAviso('')}>{aviso}</p>}
            {tela === 'inicio' && (
                <>
                    <div className="border-b border-gray-100 px-3 py-2.5">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Atendentes on-line</p>
                        {atendentes.length ? (
                            <div className="mt-1 flex flex-wrap gap-1.5">{atendentes.map((a) => (
                                <span key={a.id} className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-800">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />{a.nome}</span>))}</div>
                        ) : <p className="mt-1 text-xs text-gray-500">Nenhum atendente on-line agora. Você pode abrir o atendimento: a resposta chega aqui e por e-mail.</p>}
                    </div>
                    <div className="space-y-2 px-3 py-3">
                        <button type="button" onClick={() => setTela('assuntos')} className="w-full rounded-xl bg-cyan-700 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800">
                            <i className="fas fa-life-ring mr-1.5" />Preciso de ajuda</button>
                        <form onSubmit={retomar} className="flex gap-1.5">
                            <input value={numero} onChange={(e) => setNumero(e.target.value)} placeholder="Retomar pelo número (ex.: SUP-2026/000001)" className="flex-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs" />
                            <button type="submit" disabled={! numero.trim()} className="rounded-lg border border-gray-300 px-2.5 text-xs disabled:opacity-40">Abrir</button>
                        </form>
                        {erro && <p className="text-xs text-red-600">{erro}</p>}
                    </div>
                    <p className="px-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Meus atendimentos</p>
                    {(painel?.atendimentos || []).map((a) => (
                        <button key={a.id} type="button" onClick={() => { setAtual(a); setTela('conversa'); }} className="w-full border-b border-gray-50 px-3 py-2 text-left hover:bg-gray-50">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-semibold text-gray-800">{a.numero}</span>
                                <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${COR[a.status]}`}>{a.status_rotulo}</span>
                                {a.nao_lidas > 0 && <span className="ml-auto rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{a.nao_lidas}</span>}
                            </div>
                            <p className="truncate text-xs text-gray-600">{a.titulo}</p>
                        </button>
                    ))}
                    {! painel?.atendimentos?.length && <p className="px-3 py-2 text-xs text-gray-400">Nenhum atendimento ainda.</p>}
                    <div className="flex justify-between px-3 py-3 text-[11px]">
                        <a href="/suporte/ajuda" className="text-cyan-700 hover:underline"><i className="fas fa-book-open mr-1" />Central de ajuda</a>
                        <a href="/suporte" className="text-cyan-700 hover:underline">{painel?.eh_atendente ? `Fila de atendimento (${painel.fila})` : 'Todos os meus atendimentos'} →</a>
                    </div>
                </>
            )}
            {tela === 'assuntos' && (
                <div className="p-3">
                    <Voltar onClick={() => setTela('inicio')} />
                    <p className="mb-2 text-sm font-semibold text-gray-800">Sobre o que é sua dúvida?</p>
                    {(painel?.assuntos || []).map((a) => (
                        <button key={a.id} type="button" onClick={() => escolherAssunto(a)} className="mb-1.5 w-full rounded-lg border border-gray-200 px-3 py-2 text-left hover:border-cyan-400 hover:bg-cyan-50">
                            <span className="text-sm text-gray-800">{a.nome}</span>{a.descricao && <span className="block text-[11px] text-gray-500">{a.descricao}</span>}
                        </button>
                    ))}
                    <button type="button" onClick={() => { setAssunto(null); setTela('abrir'); }} className="mt-1 w-full rounded-lg border border-dashed border-gray-300 px-3 py-2 text-left text-sm text-gray-600">Outro assunto</button>
                </div>
            )}
            {tela === 'artigos' && (
                <div className="p-3">
                    <Voltar onClick={() => setTela('assuntos')} />
                    <p className="mb-2 text-sm font-semibold text-gray-800">{assunto?.nome}: veja se isto resolve</p>
                    {artigos.map((art) => (
                        <div key={art.id} className="mb-2 rounded-lg border border-gray-200">
                            <button type="button" onClick={() => verArtigo(art)} className="flex w-full items-center gap-2 px-3 py-2 text-left">
                                <i className="fas fa-file-lines text-cyan-700" /><span className="flex-1 text-sm text-gray-800">{art.titulo}</span>
                                <i className={`fas fa-chevron-${aberto === art.id ? 'up' : 'down'} text-[10px] text-gray-400`} /></button>
                            {aberto === art.id && (
                                <div className="border-t border-gray-100 px-3 py-2">
                                    {/* conteúdo saneado no servidor ao salvar (HtmlSeguro::limpar) */}
                                    <div className="prose prose-sm max-w-none text-xs" dangerouslySetInnerHTML={{ __html: art.conteudo }} />
                                    <p className="mt-2 text-xs font-medium text-gray-700">Isso resolveu?</p>
                                    <div className="mt-1 flex gap-1.5">
                                        <button type="button" onClick={() => retorno(art, true)} className="flex-1 rounded-lg bg-emerald-600 py-1.5 text-xs font-semibold text-white">Sim, resolveu</button>
                                        <button type="button" onClick={() => retorno(art, false)} className="flex-1 rounded-lg border border-gray-300 py-1.5 text-xs">Não, falar com o suporte</button>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                    {! artigos.length && <p className="mb-2 text-xs text-gray-500">Ainda não há artigos sobre este assunto.</p>}
                    <button type="button" onClick={() => setTela('abrir')} className="w-full rounded-lg border border-cyan-600 py-2 text-sm text-cyan-800">Falar com o suporte</button>
                </div>
            )}
            {tela === 'abrir' && (
                <form onSubmit={abrir} className="space-y-2 p-3">
                    <Voltar onClick={() => setTela(assunto ? 'artigos' : 'assuntos')} />
                    <p className="text-sm font-semibold text-gray-800">Abrir atendimento{assunto ? ` — ${assunto.nome}` : ''}</p>
                    <input value={form.titulo} onChange={(e) => setForm({ ...form, titulo: e.target.value })} maxLength={200} placeholder="Resumo do problema" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" required />
                    <textarea value={form.descricao} onChange={(e) => setForm({ ...form, descricao: e.target.value })} rows={5} placeholder="Descreva o que aconteceu, a tela e o que você tentou fazer" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" required />
                    {erro && <p className="text-xs text-red-600">{erro}</p>}
                    <button type="submit" className="w-full rounded-xl bg-cyan-700 py-2.5 text-sm font-semibold text-white">Abrir atendimento</button>
                </form>
            )}
        </div>
    );
}

const Voltar = ({ onClick }) => <button type="button" onClick={onClick} className="mb-2 text-xs text-gray-500 hover:text-gray-700"><i className="fas fa-arrow-left mr-1" />Voltar</button>;

function Conversa({ fetchJson, atual, setAtual, voltar }) {
    const [msgs, setMsgs] = useState([]);
    const [texto, setTexto] = useState('');
    const ultimo = useRef(0);
    const lista = useRef(null);

    const carregar = async (novas) => {
        try {
            const r = await fetchJson(`/suporte/api/atendimentos/${atual.id}/mensagens${novas && ultimo.current ? `?apos=${ultimo.current}` : ''}`);
            if (r.mensagens.length) {
                ultimo.current = Math.max(ultimo.current, ...r.mensagens.map((m) => m.id));
                setMsgs((p) => (novas ? [...p, ...r.mensagens.filter((m) => ! p.some((x) => x.id === m.id))] : r.mensagens));
            }
            setAtual(r.atendimento);
        } catch { /* silencioso */ }
    };
    useEffect(() => {
        ultimo.current = 0; carregar(false);
        const t = setInterval(() => carregar(true), 3000);
        return () => clearInterval(t);
    }, [atual.id]);
    useEffect(() => { if (lista.current) lista.current.scrollTop = lista.current.scrollHeight; }, [msgs.length]);

    const enviar = async (e) => {
        e.preventDefault();
        const t = texto.trim();
        if (! t) return;
        setTexto('');
        await fetchJson(`/suporte/api/atendimentos/${atual.id}/mensagens`, { method: 'POST', body: JSON.stringify({ conteudo: t }) }).catch(() => {});
        carregar(true);
    };

    return (
        <>
            <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-2">
                <button type="button" onClick={voltar} className="text-gray-500" aria-label="Voltar"><i className="fas fa-arrow-left" /></button>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-gray-800">{atual.numero} <span className={`ml-1 rounded-full px-1.5 py-0.5 text-[10px] ${COR[atual.status]}`}>{atual.status_rotulo}</span></p>
                    <p className="truncate text-[11px] text-gray-500">{atual.titulo}{atual.atendente ? ` · ${atual.atendente}` : ''}</p>
                </div>
                <a href={`/suporte/atendimentos/${atual.id}`} className="text-[11px] text-cyan-700" title="Abrir em tela cheia"><i className="fas fa-up-right-from-square" /></a>
            </div>
            <div ref={lista} className="flex-1 space-y-2 overflow-y-auto bg-gray-50 p-3">
                {msgs.map((m) => m.tipo === 'sistema'
                    ? <p key={m.id} className="text-center text-[11px] text-gray-500">{m.conteudo} · {hora(m.em)}</p>
                    : (
                        <div key={m.id} className={`flex ${m.minha ? 'justify-end' : 'justify-start'}`}>
                            <div className={`max-w-[80%] rounded-2xl px-3 py-2 text-sm ${m.tipo === 'nota' ? 'border border-amber-300 bg-amber-50 text-amber-900' : m.minha ? 'rounded-br-sm bg-cyan-700 text-white' : 'rounded-bl-sm border border-gray-200 bg-white text-gray-800'}`}>
                                {! m.minha && <p className="text-[10px] font-semibold opacity-70">{m.autor}{m.tipo === 'nota' ? ' (nota interna)' : ''}</p>}
                                <p className="whitespace-pre-wrap break-words">{m.conteudo}</p>
                                <p className={`mt-1 text-[10px] ${m.minha ? 'text-cyan-100' : 'text-gray-400'}`}>{hora(m.em)}</p>
                            </div>
                        </div>
                    ))}
            </div>
            {atual.status === 'cancelado' ? <p className="p-3 text-center text-xs text-gray-500">Atendimento cancelado.</p> : (
                <form onSubmit={enviar} className="flex items-end gap-2 border-t border-gray-100 bg-white p-2">
                    <textarea value={texto} onChange={(e) => setTexto(e.target.value)} rows={1}
                        onKeyDown={(e) => { if (e.key === 'Enter' && ! e.shiftKey) { e.preventDefault(); enviar(e); } }}
                        placeholder={['resolvido', 'fechado'].includes(atual.status) ? 'Escrever reabre o atendimento…' : 'Mensagem…'}
                        className="max-h-24 flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                    <button type="submit" disabled={! texto.trim()} className="flex h-9 w-9 items-center justify-center rounded-full bg-cyan-700 text-white disabled:opacity-50" aria-label="Enviar"><i className="fas fa-paper-plane text-xs" /></button>
                </form>
            )}
        </>
    );
}
