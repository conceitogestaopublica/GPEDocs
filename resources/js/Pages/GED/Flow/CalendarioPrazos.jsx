/**
 * Calendário mensal de prazos (TR 4.13.3): prazos lançados à mão e prazos das etapas
 * que estão comigo ou com meu setor, com feriados e dias sem expediente.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import { SITUACAO_PRAZO } from '../../../Components/PrazosDoProcesso';

const SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const FILTROS = [['todos', 'Tudo que vejo'], ['meus', 'Só meus'], ['setor', 'Do meu setor']];

export default function CalendarioPrazos({ mes, filtro, itens = [], feriados = [], dias_uteis = [1, 2, 3, 4, 5] }) {
    const [ano, m] = mes.split('-').map(Number);
    const primeiro = new Date(ano, m - 1, 1);
    const diasNoMes = new Date(ano, m, 0).getDate();
    const vazios = primeiro.getDay();
    const hoje = new Date();
    const chave = (d) => `${ano}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const porDia = itens.reduce((acc, it) => ((acc[it.dia] ||= []).push(it), acc), {});
    const feriadoDe = Object.fromEntries(feriados.map(f => [f.data, f]));
    const ir = (params) => router.get('/flow/prazos', { mes, filtro, ...params }, { preserveScroll: true });
    const mesRel = (delta) => { const d = new Date(ano, m - 1 + delta, 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; };
    const tituloMes = primeiro.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    const titulo = tituloMes.charAt(0).toUpperCase() + tituloMes.slice(1);

    return (
        <AdminLayout>
            <Head title="Calendário de prazos" />
            <PageHeader title="Calendário de prazos" subtitle="Prazos lançados nos processos e prazos das etapas que estão com você ou com seu setor" />

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <Button variant="secondary" icon="fas fa-chevron-left" onClick={() => ir({ mes: mesRel(-1) })} />
                <h2 className="text-lg font-semibold text-gray-800 min-w-[180px] text-center">{titulo}</h2>
                <Button variant="secondary" icon="fas fa-chevron-right" onClick={() => ir({ mes: mesRel(1) })} />
                <Button variant="secondary" onClick={() => ir({ mes: undefined })}>Hoje</Button>
                <div className="ml-auto inline-flex rounded-lg border border-gray-200 overflow-hidden">
                    {FILTROS.map(([v, r]) => (
                        <button key={v} type="button" onClick={() => ir({ filtro: v })}
                            className={`px-3 py-1.5 text-sm border-r last:border-r-0 border-gray-200 ${filtro === v ? 'bg-cyan-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'}`}>{r}</button>
                    ))}
                </div>
            </div>

            <div className="flex flex-wrap gap-3 mb-3 text-xs text-gray-600">
                {Object.entries(SITUACAO_PRAZO).filter(([k]) => k !== 'realizado').map(([k, s]) => (
                    <span key={k} className={`rounded-full px-2 py-0.5 ${s.cor}`}>{s.rotulo}</span>
                ))}
                <span className="rounded-full px-2 py-0.5 bg-red-50 text-red-700 border border-red-100">Feriado</span>
                <span className="text-gray-500">{itens.length} prazo(s) no mês</span>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                    {SEMANA.map(s => <div key={s} className="px-2 py-2 text-xs font-semibold text-gray-500 text-center">{s}</div>)}
                </div>
                <div className="grid grid-cols-7">
                    {Array.from({ length: vazios }).map((_, i) => <div key={`v${i}`} className="min-h-[110px] border-b border-r border-gray-100 bg-gray-50/50" />)}
                    {Array.from({ length: diasNoMes }, (_, i) => i + 1).map(d => {
                        const k = chave(d);
                        const dataDia = new Date(ano, m - 1, d);
                        const isoSemana = dataDia.getDay() === 0 ? 7 : dataDia.getDay();
                        const semExpediente = ! dias_uteis.includes(isoSemana);
                        const fer = feriadoDe[k];
                        const ehHoje = dataDia.toDateString() === hoje.toDateString();
                        return (
                            <div key={k} className={`min-h-[110px] border-b border-r border-gray-100 p-1.5 ${fer ? 'bg-red-50/60' : semExpediente ? 'bg-gray-50' : ''}`}>
                                <div className="flex items-center justify-between">
                                    <span className={`text-xs font-semibold ${ehHoje ? 'bg-cyan-600 text-white rounded-full w-6 h-6 flex items-center justify-center' : 'text-gray-600'}`}>{d}</span>
                                </div>
                                {fer && <p className="text-[10px] text-red-700 leading-tight mt-0.5" title={fer.tipo === 'ponto_facultativo' ? 'Ponto facultativo' : 'Feriado'}>{fer.nome}</p>}
                                <div className="mt-1 space-y-1">
                                    {(porDia[k] || []).map((it, i) => (
                                        <Link key={i} href={`/processos/${it.processo.id}`}
                                            title={`${it.titulo} — ${it.processo.numero}: ${it.processo.assunto} (${it.hora})`}
                                            className={`block text-[10px] leading-tight rounded px-1 py-0.5 truncate ${SITUACAO_PRAZO[it.situacao]?.cor || 'bg-gray-100'}`}>
                                            <i className={`fas ${it.tipo === 'etapa' ? 'fa-route' : 'fa-clock'} mr-0.5`} />
                                            {it.hora} {it.processo.numero} · {it.titulo}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AdminLayout>
    );
}
