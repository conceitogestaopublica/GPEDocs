/**
 * Sala de Chat Interno — botao flutuante + painel lateral.
 * Polling a cada 10s para contadores; ao abrir conversa, polling de 5s.
 * Notifica via badge, som e toast quando chega mensagem nova.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

export default function ChatFlutuante() {
    const [open, setOpen] = useState(false);
    const [contatos, setContatos] = useState([]);
    const [totalNaoLidas, setTotalNaoLidas] = useState(0);
    const [contatoAtivo, setContatoAtivo] = useState(null); // {id, nome}
    const [mensagens, setMensagens] = useState([]);
    const [texto, setTexto] = useState('');
    const [filtroBusca, setFiltroBusca] = useState('');
    const [carregando, setCarregando] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [toast, setToast] = useState(null);

    const totalRef = useRef(0);
    const contatoAtivoRef = useRef(null);
    const listaMsgRef = useRef(null);
    const ultimaMsgIdRef = useRef(0);
    const audioRef = useRef(null);

    // CSRF token
    const csrf = typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.content
        : '';

    const fetchJson = useCallback(async (url, init = {}) => {
        const opts = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(init.body ? { 'Content-Type': 'application/json' } : {}),
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                ...(init.headers || {}),
            },
            ...init,
        };
        const r = await fetch(url, opts);
        const ct = r.headers.get('content-type') || '';
        if (!r.ok || !ct.includes('application/json')) {
            // Log no console pra nao silenciar regressoes (500, redirect HTML, etc).
            // eslint-disable-next-line no-console
            console.warn('[chat] resposta nao-JSON em', url, 'status', r.status);
            throw new Error('HTTP ' + r.status);
        }
        return r.json();
    }, [csrf]);

    const tocarSom = () => {
        try { audioRef.current?.play().catch(() => {}); } catch (_) {}
    };

    const mostrarToast = (titulo, msg) => {
        setToast({ titulo, msg });
        setTimeout(() => setToast(null), 4500);
    };

    // Carrega contadores e contatos
    const carregarContatos = useCallback(async () => {
        try {
            const data = await fetchJson('/chat/contatos');
            setContatos(data.contatos || []);
            const novoTotal = data.total_nao_lidas || 0;
            // Avisa se aumentou (chegou nova msg) e o painel nao esta na conversa do remetente
            if (novoTotal > totalRef.current) {
                tocarSom();
                // Tenta achar de quem veio
                const novo = (data.contatos || []).find(c => c.nao_lidas > 0);
                if (novo && (!contatoAtivoRef.current || contatoAtivoRef.current.id !== novo.id)) {
                    mostrarToast(novo.nome, novo.ultima || 'Nova mensagem');
                }
            }
            totalRef.current = novoTotal;
            setTotalNaoLidas(novoTotal);
        } catch (_) { /* silencioso */ }
    }, [fetchJson]);

    // Apenas o contador (polling rapido em paginas onde o painel esta fechado)
    const carregarSomenteContador = useCallback(async () => {
        try {
            const data = await fetchJson('/chat/nao-lidas');
            const t = data.total || 0;
            if (t > totalRef.current) {
                tocarSom();
                // Atualiza contatos para mostrar de quem veio
                carregarContatos();
            }
            totalRef.current = t;
            setTotalNaoLidas(t);
        } catch (_) { /* silencioso */ }
    }, [fetchJson, carregarContatos]);

    // Carrega mensagens da conversa ativa.
    // Nao chama carregarContatos aqui — o polling de contatos ja roda em background.
    // Encadear duas requests em serie deixa o "abrir conversa" lento no artisan serve (mono-processo).
    const carregarMensagens = useCallback(async (contatoId, somenteNovas = false) => {
        try {
            const url = somenteNovas && ultimaMsgIdRef.current
                ? `/chat/mensagens/${contatoId}?apos=${ultimaMsgIdRef.current}`
                : `/chat/mensagens/${contatoId}`;
            const data = await fetchJson(url);
            const novas = data.mensagens || [];
            if (somenteNovas) {
                if (novas.length === 0) return;
                // Merge com dedup por id (evita duplicar quando POST otimista + poll correm juntos)
                setMensagens(prev => {
                    const ids = new Set(prev.map(m => m.id));
                    const filtradas = novas.filter(m => !ids.has(m.id));
                    return filtradas.length === 0 ? prev : [...prev, ...filtradas];
                });
            } else {
                setMensagens(novas);
            }
            if (novas.length > 0) {
                ultimaMsgIdRef.current = Math.max(...novas.map(m => m.id), ultimaMsgIdRef.current);
            }
        } catch (_) {}
    }, [fetchJson]);

    // Polling principal — 5s para o aviso de nova mensagem chegar mais rapido
    useEffect(() => {
        carregarContatos();
        const interval = setInterval(() => {
            if (open) carregarContatos();
            else carregarSomenteContador();
        }, 5000);
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // Polling de mensagens na conversa aberta — 3s
    useEffect(() => {
        contatoAtivoRef.current = contatoAtivo;
        if (!contatoAtivo) return;
        setCarregando(true);
        ultimaMsgIdRef.current = 0;
        carregarMensagens(contatoAtivo.id).finally(() => {
            setCarregando(false);
            // Refresca contadores depois da carga inicial (backend marcou como lida).
            // Disparado uma unica vez, nao em cada poll.
            carregarContatos();
        });
        const interval = setInterval(() => {
            carregarMensagens(contatoAtivo.id, true);
        }, 3000);
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contatoAtivo?.id]);

    // Auto-scroll para a ultima mensagem
    useEffect(() => {
        if (listaMsgRef.current) {
            listaMsgRef.current.scrollTop = listaMsgRef.current.scrollHeight;
        }
    }, [mensagens]);

    const enviar = async (e) => {
        e.preventDefault();
        const txt = texto.trim();
        if (!txt || !contatoAtivo || enviando) return;
        setEnviando(true);
        try {
            const data = await fetchJson(`/chat/enviar/${contatoAtivo.id}`, {
                method: 'POST',
                body: JSON.stringify({ conteudo: txt }),
            });
            if (data.mensagem) {
                setMensagens(prev =>
                    prev.some(m => m.id === data.mensagem.id)
                        ? prev
                        : [...prev, data.mensagem]
                );
                ultimaMsgIdRef.current = Math.max(ultimaMsgIdRef.current, data.mensagem.id);
            }
            setTexto('');
            carregarContatos();
        } finally {
            setEnviando(false);
        }
    };

    const contatosFiltrados = filtroBusca.trim()
        ? contatos.filter(c => c.nome.toLowerCase().includes(filtroBusca.toLowerCase()))
        : contatos;

    return (
        <>
            {/* Audio de notificacao - data URI simples (bipe curto) */}
            <audio ref={audioRef} preload="auto">
                <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav" />
            </audio>

            {/* Toast */}
            {toast && (
                <div className="fixed bottom-24 right-6 z-[100] bg-white border border-gray-200 rounded-xl shadow-xl p-3 max-w-xs animate-bounce-once cursor-pointer"
                    onClick={() => { setToast(null); setOpen(true); }}>
                    <div className="flex items-start gap-2">
                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                            <i className="fas fa-comment-dots text-blue-600 text-sm" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-semibold text-gray-800 truncate">{toast.titulo}</p>
                            <p className="text-[11px] text-gray-600 line-clamp-2">{toast.msg}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Botao flutuante */}
            {!open && (
                <button onClick={() => setOpen(true)}
                    className="fixed bottom-6 right-6 z-[90] w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 text-white shadow-xl flex items-center justify-center transition-all hover:scale-105"
                    title="Chat interno">
                    <i className="fas fa-comment-dots text-xl" />
                    {totalNaoLidas > 0 && (
                        <span className="absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1.5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center border-2 border-white animate-pulse">
                            {totalNaoLidas > 99 ? '99+' : totalNaoLidas}
                        </span>
                    )}
                </button>
            )}

            {/* Painel */}
            {open && (
                <div className="fixed bottom-6 right-6 z-[90] w-[380px] h-[560px] max-h-[85vh] bg-white border border-gray-200 rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                    {/* Header */}
                    <div className="px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white flex items-center justify-between">
                        {contatoAtivo ? (
                            <>
                                <button onClick={() => setContatoAtivo(null)}
                                    className="flex items-center gap-2 text-sm">
                                    <i className="fas fa-arrow-left" />
                                    <span className="font-semibold truncate max-w-[200px]">{contatoAtivo.nome}</span>
                                </button>
                                <button onClick={() => setOpen(false)} className="opacity-80 hover:opacity-100">
                                    <i className="fas fa-times" />
                                </button>
                            </>
                        ) : (
                            <>
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-comment-dots" />
                                    <span className="text-sm font-semibold">Chat Interno</span>
                                    {totalNaoLidas > 0 && (
                                        <span className="bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">
                                            {totalNaoLidas}
                                        </span>
                                    )}
                                </div>
                                <button onClick={() => setOpen(false)} className="opacity-80 hover:opacity-100">
                                    <i className="fas fa-times" />
                                </button>
                            </>
                        )}
                    </div>

                    {/* Conteudo */}
                    {!contatoAtivo ? (
                        // Lista de contatos
                        <>
                            <div className="px-3 py-2 border-b border-gray-100">
                                <div className="relative">
                                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                                    <input type="text" value={filtroBusca} onChange={e => setFiltroBusca(e.target.value)}
                                        placeholder="Buscar pessoa..."
                                        className="w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" />
                                </div>
                            </div>
                            <div className="flex-1 overflow-y-auto">
                                {contatosFiltrados.length === 0 && (
                                    <p className="text-xs text-gray-400 text-center py-8">
                                        {contatos.length === 0 ? 'Nenhum usuario disponivel na sua UG' : 'Nenhum resultado'}
                                    </p>
                                )}
                                {contatosFiltrados.map(c => (
                                    <button key={c.id} onClick={() => setContatoAtivo({ id: c.id, nome: c.nome })}
                                        className="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 border-b border-gray-50 text-left">
                                        <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 text-white flex items-center justify-center shrink-0 font-semibold text-xs">
                                            {iniciais(c.nome)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-medium text-gray-800 truncate">{c.nome}</p>
                                                {c.em && <span className="text-[10px] text-gray-400 shrink-0">{horario(c.em)}</span>}
                                            </div>
                                            <p className="text-[11px] text-gray-500 truncate">{c.ultima || c.email}</p>
                                        </div>
                                        {c.nao_lidas > 0 && (
                                            <span className="bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1.5 flex items-center justify-center">
                                                {c.nao_lidas > 99 ? '99+' : c.nao_lidas}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>
                        </>
                    ) : (
                        // Conversa
                        <>
                            <div ref={listaMsgRef} className="flex-1 overflow-y-auto p-3 bg-gray-50 space-y-2">
                                {carregando && (
                                    <p className="text-xs text-gray-400 text-center py-2"><i className="fas fa-spinner fa-spin" /></p>
                                )}
                                {!carregando && mensagens.length === 0 && (
                                    <p className="text-xs text-gray-400 text-center py-8">
                                        Inicie a conversa enviando uma mensagem.
                                    </p>
                                )}
                                {mensagens.map(m => {
                                    const minha = m.destinatario_id === contatoAtivo.id;
                                    return (
                                        <div key={m.id} className={`flex ${minha ? 'justify-end' : 'justify-start'}`}>
                                            <div className={`max-w-[75%] px-3 py-2 rounded-2xl text-sm
                                                ${minha ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm'}`}>
                                                <p className="whitespace-pre-wrap break-words">{m.conteudo}</p>
                                                <p className={`text-[10px] mt-1 ${minha ? 'text-blue-100' : 'text-gray-400'}`}>
                                                    {horario(m.created_at)}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            <form onSubmit={enviar} className="p-2 border-t border-gray-100 bg-white flex items-end gap-2">
                                <textarea value={texto} onChange={e => setTexto(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(e); } }}
                                    rows={1} placeholder="Mensagem..."
                                    className="flex-1 resize-none text-sm border border-gray-200 rounded-xl px-3 py-2 max-h-24 focus:outline-none focus:ring-2 focus:ring-blue-400" />
                                <button type="submit" disabled={enviando || !texto.trim()}
                                    className="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i className={`fas ${enviando ? 'fa-spinner fa-spin' : 'fa-paper-plane'} text-xs`} />
                                </button>
                            </form>
                        </>
                    )}
                </div>
            )}
        </>
    );
}

function iniciais(nome) {
    if (!nome) return '??';
    return nome.trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase() || '').join('');
}

function horario(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const hoje = new Date();
    const mesmaData = d.toDateString() === hoje.toDateString();
    return mesmaData
        ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}
