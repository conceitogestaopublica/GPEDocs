/**
 * Atendimento de suporte (TR 4.26.4–10): conversa (com notas internas dos atendentes), participantes,
 * incluir usuário ou atendente, assumir, encaminhar ao próximo nível (o 3º abre o chamado da TI), resolver
 * (e-mail de avaliação), reabrir, cancelar e o resumo com atendentes e horários.
 */
import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import Modal from '../../Components/Modal';

const COR = { aberto: 'bg-amber-100 text-amber-800', em_atendimento: 'bg-cyan-100 text-cyan-800', resolvido: 'bg-emerald-100 text-emerald-700',
    fechado: 'bg-slate-100 text-slate-600', cancelado: 'bg-red-100 text-red-700' };
const hora = (iso) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const jsonFetch = (url, opts = {}) => fetch(url, { credentials: 'same-origin', ...opts,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...(opts.headers || {}) } }).then((r) => r.json());

export default function Atendimento({ atendimento: a, participantes, ehAtendente, souSolicitante, niveis, chamadoConfigurado }) {
    const [msgs, setMsgs] = useState([]);
    const [texto, setTexto] = useState('');
    const [nota, setNota] = useState(false);
    const [modal, setModal] = useState(null);       // escalar | resolver | incluir | reabrir
    const [campo, setCampo] = useState('');
    const [pessoas, setPessoas] = useState([]);
    const ultimo = useRef(0);
    const lista = useRef(null);
    const fechado = ['resolvido', 'fechado', 'cancelado'].includes(a.status);

    const carregar = async () => {
        const r = await jsonFetch(`/suporte/api/atendimentos/${a.id}/mensagens${ultimo.current ? `?apos=${ultimo.current}` : ''}`).catch(() => null);
        if (r?.mensagens?.length) {
            ultimo.current = Math.max(ultimo.current, ...r.mensagens.map((m) => m.id));
            setMsgs((p) => [...p, ...r.mensagens.filter((m) => ! p.some((x) => x.id === m.id))]);
        }
        if (r && r.atendimento.status !== a.status) router.reload({ preserveScroll: true });
    };
    useEffect(() => { carregar(); const t = setInterval(carregar, 4000); return () => clearInterval(t); }, [a.id, a.status]);
    useEffect(() => { if (lista.current) lista.current.scrollTop = lista.current.scrollHeight; }, [msgs.length]);
    useEffect(() => {
        if (modal !== 'incluir' || campo.trim().length < 2) { setPessoas([]); return undefined; }
        const t = setTimeout(() => jsonFetch(`/suporte/api/pessoas?q=${encodeURIComponent(campo)}`).then(setPessoas).catch(() => {}), 300);
        return () => clearTimeout(t);
    }, [campo, modal]);

    const enviar = async (e) => {
        e.preventDefault();
        if (! texto.trim()) return;
        await jsonFetch(`/suporte/api/atendimentos/${a.id}/mensagens`, { method: 'POST', body: JSON.stringify({ conteudo: texto, nota }) });
        setTexto(''); setNota(false); carregar();
        if (a.status === 'aberto' && ehAtendente && ! souSolicitante) router.reload({ preserveScroll: true });
    };
    const acao = (url, dados = {}) => router.post(url, dados, { preserveScroll: true, onSuccess: () => { setModal(null); setCampo(''); } });
    const r = a.resumo;

    return (
        <AdminLayout>
            <Head title={`Suporte ${a.numero}`} />
            <PageHeader title={`${a.numero} — ${a.titulo}`} subtitle={`${a.assunto || 'Sem assunto'} · aberto por ${a.solicitante}${a.canal === 'email' ? ' por e-mail' : ''}`}>
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${COR[a.status]}`}>{a.status_rotulo}</span>
                <span className="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">{niveis[a.nivel]}</span>
            </PageHeader>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card className="xl:col-span-2" padding={false}>
                    <div ref={lista} className="h-[52vh] space-y-2 overflow-y-auto bg-gray-50 p-4">
                        {msgs.map((m) => m.tipo === 'sistema'
                            ? <p key={m.id} className="text-center text-xs text-gray-500">{m.conteudo} · {hora(m.em)}</p>
                            : (
                                <div key={m.id} className={`flex ${m.minha ? 'justify-end' : 'justify-start'}`}>
                                    <div className={`max-w-[75%] rounded-2xl px-3 py-2 text-sm ${m.tipo === 'nota' ? 'border border-amber-300 bg-amber-50 text-amber-900' : m.minha ? 'rounded-br-sm bg-cyan-700 text-white' : 'rounded-bl-sm border border-gray-200 bg-white text-gray-800'}`}>
                                        <p className="text-[11px] font-semibold opacity-75">{m.autor}{m.tipo === 'nota' ? ' — nota interna (o usuário não vê)' : ''}</p>
                                        <p className="whitespace-pre-wrap break-words">{m.conteudo}</p>
                                        <p className={`mt-1 text-[10px] ${m.minha && m.tipo !== 'nota' ? 'text-cyan-100' : 'text-gray-400'}`}>{hora(m.em)}</p>
                                    </div>
                                </div>
                            ))}
                    </div>
                    {a.status !== 'cancelado' && (
                        <form onSubmit={enviar} className="border-t border-gray-100 p-3">
                            <textarea value={texto} onChange={(e) => setTexto(e.target.value)} rows={3}
                                placeholder={fechado ? 'Escrever reabre o atendimento…' : 'Mensagem'} className="ds-input !h-auto w-full" />
                            <div className="mt-2 flex items-center gap-3">
                                {ehAtendente && ! souSolicitante && <label className="flex items-center gap-1.5 text-xs text-amber-800"><input type="checkbox" checked={nota} onChange={(e) => setNota(e.target.checked)} /> Nota interna (só atendentes)</label>}
                                <Button type="submit" size="sm" icon="fas fa-paper-plane" className="ml-auto" disabled={! texto.trim()}>Enviar</Button>
                            </div>
                        </form>
                    )}
                </Card>

                <div className="space-y-4">
                    <Card title="Ações">
                        <div className="flex flex-wrap gap-2">
                            {ehAtendente && ! souSolicitante && ! fechado && <Button size="sm" icon="fas fa-hand" onClick={() => acao(`/suporte/atendimentos/${a.id}/assumir`)}>Assumir</Button>}
                            {ehAtendente && ! souSolicitante && ! fechado && a.nivel < 3 && <Button size="sm" variant="secondary" icon="fas fa-turn-up" onClick={() => { setCampo(''); setModal('escalar'); }}>Encaminhar ao nível {a.nivel + 1}</Button>}
                            {ehAtendente && ! souSolicitante && ! fechado && <Button size="sm" variant="success" icon="fas fa-check" onClick={() => { setCampo(''); setModal('resolver'); }}>Resolver</Button>}
                            {! fechado && <Button size="sm" variant="secondary" icon="fas fa-user-plus" onClick={() => { setCampo(''); setModal('incluir'); }}>Incluir pessoa</Button>}
                            {['resolvido', 'fechado'].includes(a.status) && <Button size="sm" variant="secondary" icon="fas fa-rotate-left" onClick={() => { setCampo(''); setModal('reabrir'); }}>Reabrir</Button>}
                            {! fechado && (souSolicitante || ehAtendente) && <Button size="sm" variant="ghost" icon="fas fa-ban" onClick={() => window.confirm('Cancelar este atendimento?') && acao(`/suporte/atendimentos/${a.id}/cancelar`)}>Cancelar</Button>}
                        </div>
                        {a.processo && <p className="mt-3 rounded-lg bg-violet-50 p-2 text-xs text-violet-900">Chamado técnico da TI: <Link href={`/processos/${a.processo.id}`} className="font-semibold underline">{a.processo.numero_protocolo}</Link> ({a.processo.status})</p>}
                    </Card>
                    <Card title="Resumo">
                        <dl className="grid grid-cols-2 gap-y-1 text-xs">
                            <dt className="text-gray-500">Aberto</dt><dd>{r.aberto_em}</dd>
                            <dt className="text-gray-500">1ª resposta</dt><dd>{r.primeira_resposta_em || '—'}{r.min_primeira_resposta != null ? ` (${r.min_primeira_resposta} min)` : ''}</dd>
                            <dt className="text-gray-500">Resolvido</dt><dd>{r.resolvido_em || '—'}</dd>
                            <dt className="text-gray-500">Fechado</dt><dd>{r.fechado_em || '—'}</dd>
                            {a.avaliacao_nota && <><dt className="text-gray-500">Avaliação</dt><dd className="text-amber-600">{'★'.repeat(a.avaliacao_nota)}{a.avaliacao_comentario ? ` — ${a.avaliacao_comentario}` : ''}</dd></>}
                        </dl>
                        {r.atendentes.length > 0 && <div className="mt-2 border-t border-gray-100 pt-2 text-xs">{r.atendentes.map((x) => <p key={x.nome}><b>{x.nome}</b> · {x.de} a {x.ate} · {x.mensagens} msg</p>)}</div>}
                    </Card>
                    <Card title={`Participantes (${participantes.length})`}>
                        <ul className="space-y-1 text-xs">{participantes.map((p) => <li key={p.id}><b>{p.name}</b> <span className="text-gray-500">{p.papel === 'solicitante' ? 'solicitante' : p.papel === 'atendente' ? 'atendente' : 'convidado'}</span></li>)}</ul>
                    </Card>
                </div>
            </div>

            <Modal show={!! modal} onClose={() => setModal(null)} title={{ escalar: `Encaminhar ao ${niveis[a.nivel + 1] || ''}`, resolver: 'Resolver o atendimento', incluir: 'Incluir pessoa no atendimento', reabrir: 'Reabrir o atendimento' }[modal] || ''}>
                {modal === 'incluir' ? (
                    <div className="space-y-2">
                        <input value={campo} onChange={(e) => setCampo(e.target.value)} placeholder="Nome ou e-mail" className="ds-input" autoFocus />
                        <p className="text-xs text-gray-500">Pode ser outro usuário (para acompanhar) ou outro atendente (para ajudar).</p>
                        <ul className="divide-y divide-gray-100">{pessoas.map((p) => (
                            <li key={p.id}><button type="button" onClick={() => acao(`/suporte/atendimentos/${a.id}/incluir`, { user_id: p.id })} className="w-full px-2 py-2 text-left text-sm hover:bg-gray-50">{p.name}<span className="block text-xs text-gray-500">{p.email}</span></button></li>
                        ))}</ul>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {modal === 'escalar' && a.nivel === 2 && <p className="rounded-lg bg-violet-50 p-2 text-xs text-violet-900">No nível 3 o sistema abre um <b>chamado técnico</b> (processo) para o setor da TI com a conversa.{! chamadoConfigurado && ' Configure o setor da TI em Configurar suporte antes.'}</p>}
                        <textarea value={campo} onChange={(e) => setCampo(e.target.value)} rows={4} className="ds-input !h-auto"
                            placeholder={{ escalar: 'Por que precisa do próximo nível?', resolver: 'Solução aplicada (vai para o usuário no e-mail)', reabrir: 'O que ainda falta resolver?' }[modal]} />
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setModal(null)}>Cancelar</Button>
                            <Button disabled={! campo.trim()} onClick={() => acao(`/suporte/atendimentos/${a.id}/${modal}`, modal === 'escalar' ? { motivo: campo } : modal === 'resolver' ? { solucao: campo } : { motivo: campo })}>Confirmar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
