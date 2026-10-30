/**
 * Backup e portabilidade (TR 4.26.13–17): backups do município (banco + arquivos), verificação de cada
 * backup e da integridade do acervo, exportação completa com dicionário de dados, e o modo somente leitura.
 */
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';

const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const tam = (b) => (b == null ? '—' : b > 1e9 ? `${(b / 1e9).toFixed(1)} GB` : b > 1e6 ? `${(b / 1e6).toFixed(1)} MB` : `${Math.round(b / 1e3)} KB`);
const post = (url, dados = {}) => router.post(url, dados, { preserveScroll: true });

export default function Index({ backups, verificacoes, exportacoes, config, destino, tabelas }) {
    const [cfg, setCfg] = useState(config);
    const [aberta, setAberta] = useState(null);
    const ultimaVerif = verificacoes[0];

    return (
        <AdminLayout>
            <Head title="Backup e portabilidade" />
            <PageHeader title="Backup e portabilidade" subtitle="Backups, integridade do acervo, exportação completa e ambiente somente leitura" />

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card title="Backups" className="xl:col-span-2">
                    <p className="mb-3 text-xs text-gray-600">Automático: <b>incremental todo dia às 2h</b> (banco + arquivos novos ou alterados) e <b>completo aos domingos às 3h</b>;
                        verificação de cada backup às 5h. Destino: <b>{destino.driver === 'local' ? 'disco local (desenvolvimento)' : `${destino.driver}${destino.bucket ? ` · ${destino.bucket}` : ''}`}</b>.
                        {destino.driver === 'local' && <span className="text-amber-700"> Em produção configure outro ambiente (BACKUP_DRIVER=s3) para a redundância.</span>}</p>
                    <div className="mb-3 flex gap-2">
                        <Button size="sm" icon="fas fa-database" onClick={() => post('/configuracoes/portabilidade/backup', { tipo: 'completo' })}>Backup completo agora</Button>
                        <Button size="sm" variant="secondary" icon="fas fa-layer-group" onClick={() => post('/configuracoes/portabilidade/backup', { tipo: 'incremental' })}>Incremental agora</Button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b text-left text-xs text-gray-500"><th className="py-2">#</th><th>Tipo</th><th>Gerado</th><th>Arquivos</th><th>Tamanho</th><th>Verificação</th><th /></tr></thead>
                            <tbody>
                                {backups.map((b) => (
                                    <tr key={b.id} className="border-b border-gray-100 align-top">
                                        <td className="py-2 text-xs text-gray-500">{b.id}</td>
                                        <td>{b.tipo === 'completo' ? 'Completo' : `Incremental${b.base_id ? ` (desde #${b.base_id})` : ''}`}</td>
                                        <td className="text-xs">{dt(b.fim_em || b.inicio_em)}{b.status === 'erro' && <span className="block text-red-600">{b.erro}</span>}</td>
                                        <td className="text-xs">{b.status === 'pronto' ? `${b.arquivos} + banco` : b.status}</td>
                                        <td className="text-xs">{tam(b.tamanho)}</td>
                                        <td className="text-xs">{b.verificado_em ? (b.verificado_ok ? <span className="text-emerald-700"><i className="fas fa-circle-check mr-1" />íntegro · {dt(b.verificado_em)}</span>
                                            : <span className="text-red-700"><i className="fas fa-triangle-exclamation mr-1" />{b.erro}</span>) : b.status === 'pronto' ? 'pendente' : ''}</td>
                                        <td className="whitespace-nowrap text-right text-xs">{b.status === 'pronto' && <>
                                            <button type="button" onClick={() => post(`/configuracoes/portabilidade/backups/${b.id}/verificar`)} className="mr-2 text-cyan-700 hover:underline">verificar</button>
                                            <a href={`/configuracoes/portabilidade/backups/${b.id}/baixar`} className="text-cyan-700 hover:underline">baixar</a></>}</td>
                                    </tr>
                                ))}
                                {! backups.length && <tr><td colSpan={7} className="py-4 text-center text-gray-400">Nenhum backup ainda.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div className="space-y-4">
                    <Card title="Integridade do acervo">
                        <p className="mb-2 text-xs text-gray-600">Recalcula o SHA-256 de cada arquivo e compara com o gravado no envio (versões, anexos, PDFs assinados). Automático aos sábados às 4h.</p>
                        {ultimaVerif ? (
                            <div className="mb-2 rounded-lg bg-gray-50 p-2 text-xs">
                                <p>Última: {dt(ultimaVerif.fim_em)} — <b>{ultimaVerif.conferidos}</b> conferidos, <span className="text-emerald-700">{ultimaVerif.ok} íntegros</span>,
                                    <span className={ultimaVerif.divergentes ? 'text-red-700 font-semibold' : ''}> {ultimaVerif.divergentes} com hash diferente</span>,
                                    <span className={ultimaVerif.ausentes ? 'text-amber-700 font-semibold' : ''}> {ultimaVerif.ausentes} ausentes</span>.</p>
                                {ultimaVerif.problemas.length > 0 && <button type="button" onClick={() => setAberta(aberta ? null : ultimaVerif.id)} className="mt-1 text-cyan-700 hover:underline">{aberta ? 'esconder' : 'ver'} problemas ({ultimaVerif.problemas.length})</button>}
                                {aberta && <ul className="mt-1 max-h-48 space-y-0.5 overflow-auto">{ultimaVerif.problemas.map((p, i) => <li key={i}><code>{p.tabela}#{p.id}</code> — {p.problema}</li>)}</ul>}
                            </div>
                        ) : <p className="mb-2 text-xs text-gray-400">Nunca verificado.</p>}
                        <Button size="sm" variant="secondary" icon="fas fa-fingerprint" onClick={() => post('/configuracoes/portabilidade/acervo')}>Verificar agora</Button>
                    </Card>

                    <Card title="Exportação completa">
                        <p className="mb-2 text-xs text-gray-600">Pacote .zip com todas as {tabelas} tabelas em CSV (ids e vínculos preservados), os arquivos originais com índice, as evidências
                            das assinaturas, o <a href="/configuracoes/portabilidade/dicionario" target="_blank" rel="noreferrer" className="text-cyan-700 underline">dicionário de dados</a> e o manifesto de hashes.</p>
                        <Button size="sm" icon="fas fa-file-zipper" onClick={() => post('/configuracoes/portabilidade/exportar')}>Gerar exportação</Button>
                        <ul className="mt-3 divide-y divide-gray-100 text-xs">
                            {exportacoes.map((e) => (
                                <li key={e.id} className="py-1.5">
                                    <span className={e.status === 'pronto' ? 'text-emerald-700' : e.status === 'erro' ? 'text-red-700' : 'text-amber-700'}>{{ na_fila: 'Na fila', gerando: 'Gerando…', pronto: 'Pronta', erro: 'Erro' }[e.status]}</span>
                                    {' '}· {dt(e.em)}{e.usuario ? ` · ${e.usuario}` : ''}{e.status === 'pronto' ? ` · ${e.tabelas} tabelas, ${e.arquivos} arquivos, ${tam(e.tamanho)}` : ''}
                                    {e.status === 'pronto' && <a href={`/configuracoes/portabilidade/exportacoes/${e.id}/baixar`} className="ml-2 text-cyan-700 hover:underline">baixar</a>}
                                    {e.erro && <span className="block text-red-600">{e.erro}</span>}
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card title="Ambiente somente leitura">
                        <p className="mb-2 text-xs text-gray-600">Para o período pós-contratual: todos consultam e baixam, ninguém altera. Não há bloqueio por licença.</p>
                        {config.forcado_pelo_servidor && <p className="mb-2 text-xs text-amber-800">Ligado no servidor (GED_SOMENTE_LEITURA=true).</p>}
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={cfg.somente_leitura} onChange={(e) => setCfg({ ...cfg, somente_leitura: e.target.checked })} /> Somente leitura</label>
                        <input value={cfg.somente_leitura_motivo || ''} onChange={(e) => setCfg({ ...cfg, somente_leitura_motivo: e.target.value })} placeholder="Motivo (ex.: encerramento do contrato em …)" className="ds-input mt-2" />
                        <label className="mt-3 block text-xs text-gray-600">Guardar backups por (dias)<input type="number" min="7" max="3650" value={cfg.retencao_dias} onChange={(e) => setCfg({ ...cfg, retencao_dias: e.target.value })} className="ds-input mt-1 !w-28" /></label>
                        <Button size="sm" className="mt-3" icon="fas fa-save" onClick={() => post('/configuracoes/portabilidade/config', cfg)}>Salvar</Button>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
