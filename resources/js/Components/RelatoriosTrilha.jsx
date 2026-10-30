/**
 * Peças comuns das telas da trilha (TR 4.25.4–6, 4.25.12–16): filtros, exportação CSV (na hora ou em
 * segundo plano), os últimos relatórios gerados (7 dias) e os relatórios agendados.
 */
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Button from './Button';
import Card from './Card';
import Modal from './Modal';

const dataHora = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const SITUACAO = { na_fila: ['Na fila', 'bg-slate-100 text-slate-700'], gerando: ['Gerando…', 'bg-amber-100 text-amber-800'],
    pronto: ['Pronto', 'bg-emerald-100 text-emerald-700'], erro: ['Erro', 'bg-red-100 text-red-700'] };

/** Barra de filtros: aplica pela URL (paginação e exportação usam os mesmos filtros). */
export function Filtros({ base, filtros, setores, usuarios, children, extras }) {
    const [f, setF] = useState(filtros);
    useEffect(() => setF(filtros), [JSON.stringify(filtros)]);
    const aplicar = (e) => { e?.preventDefault(); router.get(base, Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v != null)), { preserveState: true }); };
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });

    return (
        <form onSubmit={aplicar} className="mb-3 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-8">
            <label className="text-xs text-gray-600">De<input type="date" {...campo('de')} className="ds-input mt-1" /></label>
            <label className="text-xs text-gray-600">Até<input type="date" {...campo('ate')} className="ds-input mt-1" /></label>
            <label className="text-xs text-gray-600">Setor<select {...campo('unidade_id')} className="ds-input mt-1"><option value="">Todos</option>
                {setores.map((s) => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}</select></label>
            <label className="text-xs text-gray-600">Usuário<select {...campo('usuario_id')} className="ds-input mt-1"><option value="">Todos</option>
                {usuarios.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></label>
            {extras?.(campo)}
            <label className="text-xs text-gray-600 md:col-span-2 xl:col-span-1">Buscar<input {...campo('busca')} placeholder="Texto, login, IP" className="ds-input mt-1" /></label>
            <div className="flex items-end gap-2">
                <Button type="submit" size="sm" icon="fas fa-filter">Filtrar</Button>
                {children}
            </div>
        </form>
    );
}

/** Exportar: pequeno baixa na hora; grande (ou se pedir) vai para a fila e avisa. */
export function Exportar({ tipo, filtros, total, limite }) {
    const qs = new URLSearchParams(Object.entries(filtros).filter(([, v]) => v !== '' && v != null)).toString();
    const grande = total > limite;
    return (
        <div className="flex flex-wrap items-center gap-2">
            <a href={`/relatorios/exportar/${tipo}?${qs}`} title={grande ? `Mais de ${limite} linhas: será gerado em segundo plano` : 'Baixar agora'}>
                <Button size="sm" variant="secondary" icon="fas fa-file-csv">{grande ? 'Gerar CSV (segundo plano)' : 'Exportar CSV'}</Button>
            </a>
            {! grande && <a href={`/relatorios/exportar/${tipo}?${qs}&segundo_plano=1`}><Button size="sm" variant="secondary" icon="fas fa-clock">Gerar em segundo plano</Button></a>}
        </div>
    );
}

export function Gerados({ gerados, destaque }) {
    // enquanto houver relatório na fila, atualiza a lista a cada 5 s
    const andamento = gerados.some((g) => ['na_fila', 'gerando'].includes(g.status));
    useEffect(() => {
        if (! andamento) return undefined;
        const t = setInterval(() => router.reload({ only: ['gerados'] }), 5000);
        return () => clearInterval(t);
    }, [andamento]);

    return (
        <Card title="Relatórios gerados (últimos 7 dias)">
            <ul className="divide-y divide-gray-100 text-sm">
                {gerados.map((g) => (
                    <li key={g.id} className={`py-2 ${destaque === g.id ? 'rounded bg-amber-50 px-2' : ''}`}>
                        <div className="flex items-center gap-2">
                            <b className="flex-1">{g.tipo}{g.agendado && <span className="ml-1 text-xs font-normal text-gray-500">(agendado)</span>}</b>
                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${SITUACAO[g.status]?.[1]}`}>{SITUACAO[g.status]?.[0]}</span>
                        </div>
                        <p className="text-xs text-gray-500">{g.filtros}</p>
                        <p className="text-xs text-gray-400">Pedido em {dataHora(g.pedido_em)}{g.linhas != null ? ` · ${g.linhas} linha(s)` : ''}{g.expira_em ? ` · disponível até ${dataHora(g.expira_em)}` : ''}</p>
                        {g.erro && <p className="text-xs text-red-600">{g.erro}</p>}
                        {g.url && <a href={g.url} className="text-xs font-semibold text-cyan-700 hover:underline"><i className="fas fa-download mr-1" />Baixar CSV</a>}
                    </li>
                ))}
                {! gerados.length && <li className="py-2 text-gray-500">Nenhum relatório gerado nos últimos 7 dias.</li>}
            </ul>
        </Card>
    );
}

export function Agendados({ tipo, agendados, frequencias, filtros }) {
    const [novo, setNovo] = useState(null);
    return (
        <Card title="Relatórios agendados">
            <ul className="divide-y divide-gray-100 text-sm">
                {agendados.map((a) => (
                    <li key={a.id} className={`py-2 ${a.ativo ? '' : 'opacity-60'}`}>
                        <div className="flex items-center gap-2"><b className="flex-1">{a.nome}</b>
                            <button type="button" onClick={() => router.post(`/relatorios/agendados/${a.id}/alternar`, {}, { preserveScroll: true })} className="text-xs text-cyan-700 hover:underline">{a.ativo ? 'Pausar' : 'Reativar'}</button>
                            <button type="button" onClick={() => window.confirm('Excluir o agendamento?') && router.delete(`/relatorios/agendados/${a.id}`, { preserveScroll: true })} className="text-xs text-red-600 hover:underline">Excluir</button></div>
                        <p className="text-xs text-gray-500">{a.tipo} · {a.frequencia}{a.enviar_email ? ' · por e-mail' : ''} · {a.filtros}</p>
                        <p className="text-xs text-gray-400">{a.ativo ? `Próximo: ${dataHora(a.proximo_em)}` : 'Pausado'}{a.ultimo_em ? ` · último: ${dataHora(a.ultimo_em)}` : ''}</p>
                    </li>
                ))}
                {! agendados.length && <li className="py-2 text-gray-500">Nenhum agendamento.</li>}
            </ul>
            <Button size="sm" className="mt-2" icon="fas fa-calendar-plus" onClick={() => setNovo({ nome: '', frequencia: 'semanal', enviar_email: true })}>Agendar com os filtros atuais</Button>
            <Modal show={!! novo} onClose={() => setNovo(null)} title="Agendar relatório">
                {novo && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Nome<input value={novo.nome} onChange={(e) => setNovo({ ...novo, nome: e.target.value })} className="ds-input mt-1" placeholder="Ex.: Acessos da semana — Gabinete" /></label>
                        <label className="block text-sm text-gray-700">Frequência<select value={novo.frequencia} onChange={(e) => setNovo({ ...novo, frequencia: e.target.value })} className="ds-input mt-1">
                            {Object.entries(frequencias).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={novo.enviar_email} onChange={(e) => setNovo({ ...novo, enviar_email: e.target.checked })} /> Enviar também por e-mail</label>
                        <p className="text-xs text-gray-500">Usa os filtros da tela (setor, usuário, categoria, busca). O período é o da frequência; o relatório chega às 6h e aparece em “Relatórios gerados”.</p>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setNovo(null)}>Cancelar</Button>
                            <Button icon="fas fa-save" onClick={() => router.post('/relatorios/agendados', { ...novo, tipo, filtros }, { preserveScroll: true, onSuccess: () => setNovo(null) })}>Agendar</Button></div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}
