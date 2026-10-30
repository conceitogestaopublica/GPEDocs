/**
 * Painel de indicadores (TR 4.25.1, 4.25.3, 4.25.7, 4.25.17–20, 4.29): uso do sistema, desempenho dos
 * setores, processos e assinaturas, com filtros de período, grão, setor, módulo e assunto. Cada gráfico
 * tem descrição, "como ler", PNG, CSV, XLSX e tela cheia (Components/Grafico).
 */
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Area, Bar, BarChart, CartesianGrid, Cell, ComposedChart, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Button from '../../Components/Button';
import Grafico, { CORES } from '../../Components/Grafico';

const ABAS = [['uso', 'Uso do sistema', 'fas fa-gauge'], ['setores', 'Setores', 'fas fa-sitemap'], ['processos', 'Processos', 'fas fa-folder-open'], ['assinaturas', 'Assinaturas', 'fas fa-file-signature']];
const FAIXA_COR = { ate2: '#15803d', ate3: '#22c55e', ate7: '#84cc16', ate14: '#facc15', ate45: '#f97316', mais45: '#dc2626', nunca: '#94a3b8' };
const num = (v, c = 1) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: c }));
const horas = (h) => (h == null ? '—' : h < 48 ? `${num(h)} h` : `${num(h / 24)} dias`);
const bytes = (b) => (b > 1e9 ? `${num(b / 1e9)} GB` : b > 1e6 ? `${num(b / 1e6)} MB` : `${num(b / 1e3)} KB`);
const eixoX = { tick: { fontSize: 11 }, interval: 'preserveStartEnd' };
const tip = { contentStyle: { fontSize: 12 } };
// legenda na ordem das séries (o Recharts 3 ordena por nome)
const semOrdenar = () => 0;

function Cartao({ rotulo, valor, detalhe, icone }) {
    return (
        <div className="rounded-xl border border-[var(--ds-border)] bg-white p-4">
            <p className="text-xs text-gray-500"><i className={`${icone} mr-1 text-cyan-700`} />{rotulo}</p>
            <p className="mt-1 text-2xl font-semibold text-gray-800">{valor}</p>
            {detalhe && <p className="text-xs text-gray-400">{detalhe}</p>}
        </div>
    );
}

function Tabela({ colunas, linhas, max = 400 }) {
    return (
        <div className="max-h-[420px] overflow-auto">
            <table className="w-full text-xs">
                <thead className="sticky top-0 bg-gray-50"><tr>{colunas.map((c) => <th key={c.key} className={`px-2 py-1.5 text-left font-semibold text-gray-600 ${c.num ? 'text-right' : ''}`}>{c.label}</th>)}</tr></thead>
                <tbody>{linhas.slice(0, max).map((l, i) => (
                    <tr key={i} className="border-b border-gray-100">{colunas.map((c) => <td key={c.key} className={`px-2 py-1.5 ${c.num ? 'text-right tabular-nums' : ''}`}>{c.render ? c.render(l) : (l[c.key] ?? '—')}</td>)}</tr>
                ))}</tbody>
            </table>
        </div>
    );
}

const Barras = ({ dados, chave, series, h, horizontal = false, empilhado = false, pct = false }) => (
    <ResponsiveContainer width="100%" height={horizontal ? Math.max(h, dados.length * 30 + 40) : h}>
        <BarChart data={dados} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ left: horizontal ? 10 : 0, right: 10 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
            {horizontal ? <><XAxis type="number" tick={{ fontSize: 11 }} domain={pct ? [0, 100] : undefined} /><YAxis type="category" dataKey={chave} width={170} interval={0} tick={{ fontSize: 11 }} /></>
                : <><XAxis dataKey={chave} {...eixoX} /><YAxis tick={{ fontSize: 11 }} domain={pct ? [0, 100] : undefined} allowDecimals={! pct ? false : true} /></>}
            <Tooltip {...tip} formatter={(v) => (pct ? `${num(v)}%` : num(v))} />
            {series.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />}
            {series.map(([k, r], i) => <Bar isAnimationActive={false} key={k} dataKey={k} name={r} fill={CORES[i % CORES.length]} stackId={empilhado ? 'a' : undefined} radius={empilhado ? 0 : [3, 3, 0, 0]} />)}
        </BarChart>
    </ResponsiveContainer>
);

const Pizza = ({ dados, chave, valor, h }) => (
    <ResponsiveContainer width="100%" height={h}>
        <PieChart>
            <Pie isAnimationActive={false} data={dados} dataKey={valor} nameKey={chave} innerRadius="45%" outerRadius="75%" paddingAngle={2} label={({ percent }) => (percent > 0.03 ? `${Math.round(percent * 100)}%` : '')}>
                {dados.map((_, i) => <Cell key={i} fill={CORES[i % CORES.length]} />)}
            </Pie>
            <Tooltip {...tip} /><Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />
        </PieChart>
    </ResponsiveContainer>
);

export default function Indicadores({ dados, filtros, setores, modulos, assuntos, aba: abaInicial }) {
    const [f, setF] = useState(filtros);
    const [aba, setAba] = useState(abaInicial);
    const aplicar = (extra = {}) => router.get('/relatorios/indicadores', { ...Object.fromEntries(Object.entries({ ...f, ...extra }).filter(([, v]) => v !== '' && v != null)), aba }, { preserveScroll: true });
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const { uso, setores: st, processos: pr, assinaturas: as } = dados;

    return (
        <AdminLayout>
            <Head title="Painel de indicadores" />
            <PageHeader title="Painel de indicadores" subtitle="Uso do sistema, desempenho dos setores, processos e assinaturas">
                <span className="text-xs text-gray-500">Calculado em {new Date(dados.calculado_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}{dados.reaproveitado ? ' (reaproveitado)' : ''}</span>
                <Button size="sm" variant="secondary" icon="fas fa-rotate" onClick={() => aplicar({ recalcular: 1 })}>Recalcular</Button>
            </PageHeader>

            <form onSubmit={(e) => { e.preventDefault(); aplicar(); }} className="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-[var(--ds-border)] bg-white p-3 md:grid-cols-4 xl:grid-cols-7">
                <label className="text-xs text-gray-600">De<input type="date" {...campo('de')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Até<input type="date" {...campo('ate')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Agrupar por<select {...campo('grao')} className="ds-input mt-1">
                    <option value="dia">Dia</option><option value="semana">Semana</option><option value="mes">Mês</option><option value="ano">Ano</option></select></label>
                <label className="text-xs text-gray-600">Setor<select {...campo('unidade_id')} className="ds-input mt-1"><option value="">Todos</option>
                    {setores.map((s) => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}</select></label>
                <label className="text-xs text-gray-600">Módulo<select {...campo('modulo')} className="ds-input mt-1"><option value="">Todos</option>
                    {Object.entries(modulos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">Assunto<select {...campo('tipo')} className="ds-input mt-1"><option value="">Todos</option>
                    {assuntos.filter((a) => ! f.modulo || (a.modalidade || 'protocolo') === f.modulo).map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}</select></label>
                <div className="flex items-end"><Button type="submit" size="sm" icon="fas fa-filter">Aplicar</Button></div>
            </form>

            <div className="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
                {ABAS.map(([k, r, ic]) => (
                    <button key={k} type="button" onClick={() => setAba(k)} className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${aba === k ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                        <i className={`${ic} mr-1.5`} />{r}
                    </button>
                ))}
            </div>

            {aba === 'uso' && (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                        <Cartao icone="fas fa-user-check" rotulo="Servidores habilitados" valor={num(uso.cartoes.servidores, 0)} detalhe={filtros.unidade_id ? 'no setor filtrado' : 'na unidade gestora'} />
                        <Cartao icone="fas fa-users" rotulo="Usuários externos" valor={num(uso.cartoes.externos, 0)} detalhe="cidadãos e externos ativos" />
                        <Cartao icone="fas fa-sitemap" rotulo="Setores ativos" valor={num(uso.cartoes.setores, 0)} />
                        <Cartao icone="fas fa-right-to-bracket" rotulo="Acessos no período" valor={num(uso.cartoes.acessos, 0)} />
                        <Cartao icone="fas fa-folder-open" rotulo="Processos" valor={num(uso.cartoes.processos, 0)} detalhe="no escopo dos filtros" />
                        <Cartao icone="fas fa-archive" rotulo="Documentos no GED" valor={num(uso.cartoes.documentos_ged, 0)} />
                        <Cartao icone="fas fa-paperclip" rotulo="Anexos" valor={num(uso.cartoes.anexos, 0)} detalhe={bytes(uso.cartoes.anexos_bytes)} />
                        <Cartao icone="fas fa-file-lines" rotulo="Páginas anexadas" valor={num(uso.cartoes.paginas, 0)} />
                        <Cartao icone="fas fa-pen-nib" rotulo="Nato-digitais no período" valor={num(uso.cartoes.nato_digitais, 0)} detalhe="redigidos no sistema" />
                        <Cartao icone="fas fa-clock" rotulo="Tempo de operação" valor={`${num(uso.cartoes.operacao_dias, 0)} dias`} detalhe={uso.cartoes.operacao_desde ? `desde ${new Date(uso.cartoes.operacao_desde).toLocaleDateString('pt-BR')}` : ''} />
                    </div>
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <Grafico titulo="Consumo mensal" descricao="Servidores que entraram, externos novos, processos, anexos e assinaturas por mês." dados={uso.consumo}
                            colunas={[{ key: 'periodo', label: 'Mês' }, { key: 'servidores_ativos', label: 'Servidores ativos' }, { key: 'externos_novos', label: 'Externos novos' }, { key: 'processos', label: 'Processos' }, { key: 'anexos', label: 'Anexos' }, { key: 'assinaturas', label: 'Assinaturas' }]}
                            comoLer="Cada linha é uma grandeza contada no mês. Servidores ativos = servidores distintos que entraram no sistema no mês.">
                            {(h) => (
                                <ResponsiveContainer width="100%" height={h}>
                                    <LineChart data={uso.consumo}><CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" /><XAxis dataKey="periodo" {...eixoX} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip {...tip} /><Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />
                                        {[['servidores_ativos', 'Servidores ativos'], ['externos_novos', 'Externos novos'], ['processos', 'Processos'], ['anexos', 'Anexos'], ['assinaturas', 'Assinaturas']].map(([k, r], i) => <Line isAnimationActive={false} key={k} dataKey={k} name={r} stroke={CORES[i]} strokeWidth={2} dot={false} />)}
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </Grafico>
                        <Grafico titulo="Usuários por nível de acesso" descricao="Servidores habilitados por perfil." dados={uso.niveis} colunas={[{ key: 'nivel', label: 'Perfil' }, { key: 'usuarios', label: 'Usuários' }]}
                            comoLer="Um servidor com mais de um perfil conta em cada um deles.">
                            {(h) => <Barras dados={uso.niveis} chave="nivel" series={[['usuarios', 'Usuários']]} h={h} horizontal />}
                        </Grafico>
                        <Grafico titulo="Documentos por setor" descricao="Processos abertos no período pelo setor de quem abriu: em trâmite e concluídos." dados={uso.por_setor}
                            colunas={[{ key: 'setor', label: 'Setor' }, { key: 'emitidos', label: 'Emitidos' }, { key: 'em_tramite', label: 'Em trâmite' }, { key: 'concluidos', label: 'Concluídos' }]}
                            comoLer="A barra inteira é o total emitido pelo setor; a cor separa o que ainda tramita do que já foi concluído.">
                            {(h) => <Barras dados={uso.por_setor} chave="setor" series={[['em_tramite', 'Em trâmite'], ['concluidos', 'Concluídos']]} h={h} horizontal empilhado />}
                        </Grafico>
                        <Grafico titulo="Documentos por assunto" descricao="Os 15 assuntos com mais processos abertos no período." dados={uso.por_tipo} colunas={[{ key: 'assunto', label: 'Assunto' }, { key: 'documentos', label: 'Processos' }]}>
                            {(h) => <Barras dados={uso.por_tipo} chave="assunto" series={[['documentos', 'Processos']]} h={h} horizontal />}
                        </Grafico>
                        <Grafico titulo="Documentos nato-digitais" descricao="Redigidos e assinados dentro do sistema no período (sem papel)." dados={uso.nato_digitais} colunas={[{ key: 'tipo', label: 'Tipo' }, { key: 'documentos', label: 'Documentos' }]}>
                            {(h) => <Barras dados={uso.nato_digitais} chave="tipo" series={[['documentos', 'Documentos']]} h={h} />}
                        </Grafico>
                        <Grafico titulo={`Setores sem movimentação (${uso.setores_inativos.length})`} descricao="Setores com servidores que não abriram nem receberam processo no período." tabela
                            dados={uso.setores_inativos.map((s) => ({ setor: s }))} colunas={[{ key: 'setor', label: 'Setor' }]}>
                            {() => <Tabela colunas={[{ key: 'setor', label: 'Setor' }]} linhas={uso.setores_inativos.map((s) => ({ setor: s }))} />}
                        </Grafico>
                    </div>
                </div>
            )}

            {aba === 'setores' && <AbaSetores st={st} />}

            {aba === 'processos' && (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Cartao icone="fas fa-plus" rotulo="Criados no período" valor={num(pr.serie.reduce((s, l) => s + l.criados, 0), 0)} />
                        <Cartao icone="fas fa-inbox" rotulo="Recebidos pelos setores" valor={num(pr.serie.reduce((s, l) => s + l.recebidos, 0), 0)} />
                        <Cartao icone="fas fa-check-double" rotulo="Resolvidos no período" valor={num(pr.serie.reduce((s, l) => s + l.resolvidos, 0), 0)} />
                        <Cartao icone="fas fa-hourglass-half" rotulo="Tempo médio de resolução" valor={pr.dias_resolucao == null ? '—' : `${num(pr.dias_resolucao)} dias`} detalhe="da abertura à conclusão" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <Grafico className="xl:col-span-2" titulo="Processos ao longo do tempo" descricao="Criados, recebidos pelos setores e resolvidos em cada período." dados={pr.serie}
                            colunas={[{ key: 'periodo', label: 'Período' }, { key: 'criados', label: 'Criados' }, { key: 'recebidos', label: 'Recebidos' }, { key: 'resolvidos', label: 'Resolvidos' }]}
                            comoLer="Recebidos conta cada envio a um setor (um processo que passa por três setores conta três vezes). Use 'Agrupar por' para ver por dia, semana, mês ou ano.">
                            {(h) => (
                                <ResponsiveContainer width="100%" height={h}>
                                    <LineChart data={pr.serie}><CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" /><XAxis dataKey="periodo" {...eixoX} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip {...tip} /><Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />
                                        <Line isAnimationActive={false} dataKey="criados" name="Criados" stroke={CORES[0]} strokeWidth={2} dot={false} /><Line isAnimationActive={false} dataKey="recebidos" name="Recebidos" stroke={CORES[1]} strokeWidth={2} dot={false} /><Line isAnimationActive={false} dataKey="resolvidos" name="Resolvidos" stroke={CORES[2]} strokeWidth={2} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </Grafico>
                        <Grafico titulo="Processos por assunto" descricao="Os 15 assuntos com mais processos: em aberto e resolvidos." dados={pr.por_assunto}
                            colunas={[{ key: 'assunto', label: 'Assunto' }, { key: 'em_aberto', label: 'Em aberto' }, { key: 'resolvidos', label: 'Resolvidos' }]}>
                            {(h) => <Barras dados={pr.por_assunto} chave="assunto" series={[['em_aberto', 'Em aberto'], ['resolvidos', 'Resolvidos']]} h={h} horizontal empilhado />}
                        </Grafico>
                        <Grafico titulo="Processos por módulo" descricao="Divisão dos processos do período entre os módulos." dados={pr.por_modulo}
                            colunas={[{ key: 'modulo', label: 'Módulo' }, { key: 'processos', label: 'Processos' }, { key: 'resolvidos', label: 'Resolvidos' }]}>
                            {(h) => <Pizza dados={pr.por_modulo} chave="modulo" valor="processos" h={h} />}
                        </Grafico>
                        <Grafico titulo="Recebidos e resolvidos por setor" descricao="Envios recebidos por setor no período e quantos ele já despachou ou concluiu." dados={pr.recebidos_setor}
                            colunas={[{ key: 'setor', label: 'Setor' }, { key: 'recebidos', label: 'Recebidos' }, { key: 'resolvidos', label: 'Resolvidos' }]}>
                            {(h) => <Barras dados={pr.recebidos_setor} chave="setor" series={[['recebidos', 'Recebidos'], ['resolvidos', 'Resolvidos']]} h={h} horizontal />}
                        </Grafico>
                        <Grafico titulo="Anexos por setor" descricao="Arquivos juntados aos processos no período, pelo setor de quem anexou, com as páginas." dados={pr.anexos_setor}
                            colunas={[{ key: 'setor', label: 'Setor' }, { key: 'anexos', label: 'Anexos' }, { key: 'paginas', label: 'Páginas' }]}>
                            {(h) => <Barras dados={pr.anexos_setor} chave="setor" series={[['anexos', 'Anexos'], ['paginas', 'Páginas']]} h={h} horizontal />}
                        </Grafico>
                        <Grafico titulo="Páginas anexadas (acumulado)" descricao="Páginas anexadas em cada período e o total acumulado." dados={pr.paginas}
                            colunas={[{ key: 'periodo', label: 'Período' }, { key: 'paginas', label: 'Páginas no período' }, { key: 'acumulado', label: 'Acumulado' }]}
                            comoLer="As barras são as páginas do período; a área é o total desde o início, incluindo o que foi anexado antes do período filtrado.">
                            {(h) => (
                                <ResponsiveContainer width="100%" height={h}>
                                    <ComposedChart data={pr.paginas}><CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" /><XAxis dataKey="periodo" {...eixoX} /><YAxis allowDecimals={false} tick={{ fontSize: 11 }} /><Tooltip {...tip} /><Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />
                                        <Area isAnimationActive={false} dataKey="acumulado" name="Acumulado" fill="#cffafe" stroke={CORES[0]} /><Bar isAnimationActive={false} dataKey="paginas" name="No período" fill={CORES[1]} />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            )}
                        </Grafico>
                        <Grafico titulo="Situação de arquivamento" descricao="Em quantos setores o processo já foi encerrado (todos, parte ou nenhum)." dados={pr.arquivamento}
                            colunas={[{ key: 'situacao', label: 'Situação' }, { key: 'processos', label: 'Processos' }]}
                            comoLer="'Todos' = nenhuma pendência em setor algum; 'parte' = encerrado em alguns setores e ainda pendente em outros; 'nenhum' = pendente em todos os setores por onde passou.">
                            {(h) => <Pizza dados={pr.arquivamento} chave="situacao" valor="processos" h={h} />}
                        </Grafico>
                        <Grafico className="xl:col-span-2" titulo="Tempos por módulo e assunto" tabela dados={pr.tempos} descricao="Médias desde a abertura do processo."
                            colunas={[{ key: 'modulo', label: 'Módulo' }, { key: 'assunto', label: 'Assunto' }, { key: 'processos', label: 'Processos' }, { key: 'resolvidos', label: 'Resolvidos' }, { key: 'h_leitura', label: 'Até a 1ª leitura (h)' }, { key: 'h_acao', label: 'Até a 1ª ação (h)' }, { key: 'h_resolucao', label: 'Até a resolução (h)' }]}
                            comoLer="1ª leitura = primeira vez que um setor abriu o processo recebido; 1ª ação = primeiro despacho ou resolução; resolução = conclusão do processo.">
                            {() => <Tabela linhas={pr.tempos} colunas={[{ key: 'modulo', label: 'Módulo' }, { key: 'assunto', label: 'Assunto' }, { key: 'processos', label: 'Processos', num: true }, { key: 'resolvidos', label: 'Resolvidos', num: true },
                                { key: 'h_leitura', label: 'Até a 1ª leitura', num: true, render: (l) => horas(l.h_leitura) }, { key: 'h_acao', label: 'Até a 1ª ação', num: true, render: (l) => horas(l.h_acao) },
                                { key: 'h_resolucao', label: 'Até a resolução', num: true, render: (l) => horas(l.h_resolucao) }]} />}
                        </Grafico>
                        <Grafico titulo="Resolução por servidor" tabela dados={pr.por_servidor} descricao="Envios recebidos pelo servidor, quantos resolveu e em quanto tempo."
                            colunas={[{ key: 'servidor', label: 'Servidor' }, { key: 'recebidas', label: 'Recebidas' }, { key: 'resolvidas', label: 'Resolvidas' }, { key: 'pct', label: '% resolvido' }, { key: 'h_resolucao', label: 'Tempo médio (h)' }]}>
                            {() => <Tabela linhas={pr.por_servidor} colunas={[{ key: 'servidor', label: 'Servidor' }, { key: 'recebidas', label: 'Recebidas', num: true }, { key: 'resolvidas', label: 'Resolvidas', num: true },
                                { key: 'pct', label: '% resolvido', num: true, render: (l) => (l.pct == null ? '—' : `${num(l.pct)}%`) }, { key: 'h_resolucao', label: 'Tempo médio', num: true, render: (l) => horas(l.h_resolucao) }]} />}
                        </Grafico>
                        <Grafico titulo="Processos por módulo e assunto" tabela dados={pr.lista} descricao="Os processos do período com data de criação e situação (até 500)."
                            colunas={[{ key: 'numero', label: 'Número' }, { key: 'modulo', label: 'Módulo' }, { key: 'assunto', label: 'Assunto' }, { key: 'criado_em', label: 'Criado em' }, { key: 'status', label: 'Situação' }]}>
                            {() => <Tabela linhas={pr.lista} colunas={[{ key: 'numero', label: 'Número', render: (l) => <a href={`/processos/${l.id}`} className="text-cyan-700 hover:underline">{l.numero}</a> },
                                { key: 'modulo', label: 'Módulo' }, { key: 'assunto', label: 'Assunto' }, { key: 'criado_em', label: 'Criado em' }, { key: 'status', label: 'Situação' }]} />}
                        </Grafico>
                    </div>
                </div>
            )}

            {aba === 'assinaturas' && (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Cartao icone="fas fa-paper-plane" rotulo="Solicitadas no período" valor={num(as.cartoes.solicitadas, 0)} />
                        <Cartao icone="fas fa-file-signature" rotulo="Realizadas no período" valor={num(as.cartoes.assinadas, 0)} />
                        <Cartao icone="fas fa-hourglass-half" rotulo="Pendentes (agora)" valor={num(as.cartoes.pendentes, 0)} />
                        <Cartao icone="fas fa-stopwatch" rotulo="Tempo médio para assinar" valor={horas(as.cartoes.h_media)} detalhe="da solicitação à assinatura" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <Grafico titulo="Assinaturas solicitadas e realizadas" descricao="Por período." dados={as.serie}
                            colunas={[{ key: 'periodo', label: 'Período' }, { key: 'solicitadas', label: 'Solicitadas' }, { key: 'assinadas', label: 'Realizadas' }]}>
                            {(h) => <Barras dados={as.serie} chave="periodo" series={[['solicitadas', 'Solicitadas'], ['assinadas', 'Realizadas']]} h={h} />}
                        </Grafico>
                        <Grafico titulo="Assinaturas por setor" descricao="Realizadas no período, pelo setor de quem assinou." dados={as.por_setor} colunas={[{ key: 'setor', label: 'Setor' }, { key: 'assinaturas', label: 'Assinaturas' }]}>
                            {(h) => <Barras dados={as.por_setor} chave="setor" series={[['assinaturas', 'Assinaturas']]} h={h} horizontal />}
                        </Grafico>
                        <Grafico className="xl:col-span-2" titulo="Assinaturas por servidor" tabela dados={as.por_servidor} descricao="Pedidos de assinatura no período por signatário, com o tempo médio e o tempo total até assinar."
                            colunas={[{ key: 'servidor', label: 'Signatário' }, { key: 'solicitadas', label: 'Solicitadas' }, { key: 'assinadas', label: 'Assinadas' }, { key: 'pendentes', label: 'Pendentes' }, { key: 'h_media', label: 'Tempo médio (h)' }, { key: 'h_total', label: 'Tempo total (h)' }]}>
                            {() => <Tabela linhas={as.por_servidor} colunas={[{ key: 'servidor', label: 'Signatário' }, { key: 'solicitadas', label: 'Solicitadas', num: true }, { key: 'assinadas', label: 'Assinadas', num: true },
                                { key: 'pendentes', label: 'Pendentes', num: true }, { key: 'h_media', label: 'Tempo médio', num: true, render: (l) => horas(l.h_media) }, { key: 'h_total', label: 'Tempo total', num: true, render: (l) => horas(l.h_total) }]} />}
                        </Grafico>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

function AbaSetores({ st }) {
    const [busca, setBusca] = useState('');
    const top = st.linhas.filter((l) => l.recebidos > 0).slice(0, 15);
    const comServidores = [...st.linhas].filter((l) => l.servidores > 0).sort((a, b) => b.servidores - a.servidores).slice(0, 15);
    const inativos = st.inativos.filter((u) => ! busca || `${u.nome} ${u.email} ${u.setor}`.toLowerCase().includes(busca.toLowerCase()));
    const faixas = Object.entries(st.faixas);

    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <Grafico titulo="Eficiência por setor" descricao="% dos envios recebidos no período que o setor já despachou ou concluiu." dados={top}
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'recebidos', label: 'Recebidos' }, { key: 'resolvidos', label: 'Resolvidos' }, { key: 'eficiencia', label: 'Eficiência (%)' }]}
                comoLer="Eficiência = resolvidos ÷ recebidos. Setores sem nenhum recebimento no período não aparecem.">
                {(h) => <Barras dados={top} chave="setor" series={[['eficiencia', 'Eficiência']]} h={h} horizontal pct />}
            </Grafico>
            <Grafico titulo="Engajamento por setor" descricao="% dos envios recebidos que alguém do setor abriu (leu)." dados={top}
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'recebidos', label: 'Recebidos' }, { key: 'engajamento', label: 'Engajamento (%)' }]}
                comoLer="Engajamento = recebidos lidos ÷ recebidos. Lido = aberto por alguém do setor ou recebido formalmente.">
                {(h) => <Barras dados={top} chave="setor" series={[['engajamento', 'Engajamento']]} h={h} horizontal pct />}
            </Grafico>
            <Grafico titulo="Qualidade dos serviços" descricao="Nota média (1 a 5) das avaliações dos processos que passaram pelo setor." dados={st.linhas.filter((l) => l.qualidade != null)}
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'qualidade', label: 'Nota média' }]}
                comoLer="As notas vêm da avaliação do cidadão (portal, e-mail ou balcão). Sem avaliação no período, o setor não aparece.">
                {(h) => <Barras dados={st.linhas.filter((l) => l.qualidade != null)} chave="setor" series={[['qualidade', 'Nota média']]} h={h} horizontal />}
            </Grafico>
            <Grafico titulo="Acesso dos servidores" descricao="Servidores habilitados pelo último acesso: hoje, 7 dias, 30 dias, mais de 30 dias ou nunca." dados={st.acesso_janela}
                colunas={[{ key: 'faixa', label: 'Último acesso' }, { key: 'servidores', label: 'Servidores' }, { key: 'pct', label: '%' }]}>
                {(h) => <Pizza dados={st.acesso_janela} chave="faixa" valor="servidores" h={h} />}
            </Grafico>
            <Grafico className="xl:col-span-2" titulo="Assiduidade por setor" descricao="Servidores de cada setor pela cor do tempo desde o último acesso." dados={comServidores}
                colunas={[{ key: 'setor', label: 'Setor' }, ...faixas.map(([k, r]) => ({ key: k, label: r }))]}
                comoLer="Verde escuro = entrou nos últimos 2 dias; vermelho = mais de 45 dias; cinza = nunca entrou. Mostra os 15 setores com mais servidores.">
                {(h) => (
                    <ResponsiveContainer width="100%" height={Math.max(h, comServidores.length * 34)}>
                        <BarChart data={comServidores} layout="vertical" margin={{ left: 10, right: 10 }}><CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} /><YAxis type="category" dataKey="setor" width={170} interval={0} tick={{ fontSize: 11 }} /><Tooltip {...tip} /><Legend wrapperStyle={{ fontSize: 12 }} itemSorter={semOrdenar} />
                            {faixas.map(([k, r]) => <Bar isAnimationActive={false} key={k} dataKey={k} name={r} stackId="a" fill={FAIXA_COR[k]} />)}
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </Grafico>
            <Grafico titulo="Servidores frequentes × total" descricao="Frequente = entrou na última semana." dados={comServidores}
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'frequentes', label: 'Frequentes' }, { key: 'servidores', label: 'Total' }]}>
                {(h) => <Barras dados={comServidores} chave="setor" series={[['frequentes', 'Frequentes'], ['servidores', 'Total']]} h={h} horizontal />}
            </Grafico>
            <Grafico titulo="Inatividade média por setor" descricao="Média de dias desde o último acesso dos servidores que já entraram." dados={comServidores.filter((l) => l.inatividade_media != null)}
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'inatividade_media', label: 'Dias (média)' }]}>
                {(h) => <Barras dados={comServidores.filter((l) => l.inatividade_media != null)} chave="setor" series={[['inatividade_media', 'Dias']]} h={h} horizontal />}
            </Grafico>
            <Grafico className="xl:col-span-2" titulo="Indicadores por setor" tabela dados={st.linhas} descricao="Todos os setores com servidores ou recebimentos no período."
                colunas={[{ key: 'setor', label: 'Setor' }, { key: 'servidores', label: 'Servidores' }, { key: 'frequentes', label: 'Frequentes' }, { key: 'recebidos', label: 'Recebidos' }, { key: 'resolvidos', label: 'Resolvidos' },
                    { key: 'eficiencia', label: 'Eficiência (%)' }, { key: 'engajamento', label: 'Engajamento (%)' }, { key: 'qualidade', label: 'Qualidade' }, { key: 'inatividade_media', label: 'Inatividade média (dias)' }]}>
                {() => <Tabela linhas={st.linhas} colunas={[{ key: 'setor', label: 'Setor' }, { key: 'servidores', label: 'Servidores', num: true }, { key: 'frequentes', label: 'Frequentes', num: true },
                    { key: 'recebidos', label: 'Recebidos', num: true }, { key: 'resolvidos', label: 'Resolvidos', num: true },
                    { key: 'eficiencia', label: 'Eficiência', num: true, render: (l) => (l.eficiencia == null ? '—' : `${num(l.eficiencia)}%`) },
                    { key: 'engajamento', label: 'Engajamento', num: true, render: (l) => (l.engajamento == null ? '—' : `${num(l.engajamento)}%`) },
                    { key: 'qualidade', label: 'Qualidade', num: true, render: (l) => num(l.qualidade) }, { key: 'inatividade_media', label: 'Inatividade (dias)', num: true, render: (l) => num(l.inatividade_media) }]} />}
            </Grafico>
            <Grafico className="xl:col-span-2" titulo="Dias de inatividade por servidor" tabela dados={st.inativos} descricao="Busca nominal: do servidor há mais tempo sem entrar para o mais ativo."
                colunas={[{ key: 'nome', label: 'Servidor' }, { key: 'email', label: 'E-mail' }, { key: 'setor', label: 'Setor' }, { key: 'dias', label: 'Dias sem entrar' }]}>
                {() => (
                    <>
                        <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar servidor, e-mail ou setor" className="ds-input mb-2" />
                        <Tabela linhas={inativos} colunas={[{ key: 'nome', label: 'Servidor' }, { key: 'email', label: 'E-mail' }, { key: 'setor', label: 'Setor' },
                            { key: 'ultimo', label: 'Último acesso', render: (u) => (u.ultimo ? new Date(u.ultimo).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Nunca') },
                            { key: 'dias', label: 'Dias sem entrar', num: true, render: (u) => (u.dias == null ? 'nunca entrou' : num(u.dias, 0)) }]} />
                    </>
                )}
            </Grafico>
        </div>
    );
}
