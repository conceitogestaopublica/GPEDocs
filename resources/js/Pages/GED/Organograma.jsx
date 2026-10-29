/**
 * Organograma (TR 4.12): árvore dos setores com quem está em cada um — online agora,
 * cargo, cadastro e último acesso —, busca por servidor, setor ou sigla, lista de
 * telefones e, para quem administra UGs, desativar/reativar e publicar no site.
 */
import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import DesativarSetorModal, { setoresAtivos } from '../../Components/DesativarSetorModal';

const sem = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default function Organograma({ ug, arvore = [], servidores = {}, gerencia = false, link = null }) {
    const [aba, setAba] = useState('estrutura');
    const [busca, setBusca] = useState('');
    const [inativos, setInativos] = useState(false);
    const [desativando, setDesativando] = useState(null);
    const labels = { 1: ug.nivel_1_label, 2: ug.nivel_2_label, 3: ug.nivel_3_label };

    const todos = useMemo(() => achatar(arvore), [arvore]);
    const totalServidores = useMemo(() => new Set(Object.values(servidores).flat().map(s => s.id)).size, [servidores]);
    const online = useMemo(() => new Set(Object.values(servidores).flat().filter(s => s.online).map(s => s.id)).size, [servidores]);

    // Busca: setores cujo nome/sigla casa, ou que têm servidor com o nome; os de cima ficam visíveis
    const termo = sem(busca.trim());
    const visiveis = useMemo(() => {
        if (! termo) return null;
        const ids = new Set();
        todos.forEach(n => {
            if (! n.ativo && ! inativos) return;
            const casa = sem(n.nome).includes(termo) || sem(n.sigla).includes(termo)
                || (servidores[n.id] || []).some(s => sem(s.nome).includes(termo));
            if (casa) n.ancestrais.concat(n.id).forEach(id => ids.add(id));
        });
        return ids;
    }, [termo, todos, servidores, inativos]);

    return (
        <AdminLayout>
            <Head title="Organograma" />
            <PageHeader title="Organograma" subtitle={`${ug.nome} — setores, servidores e telefones`} />

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <Indicador icone="fa-sitemap" rotulo="Setores ativos" valor={todos.filter(n => n.ativo).length} />
                <Indicador icone="fa-users" rotulo="Servidores" valor={totalServidores} />
                <Indicador icone="fa-circle text-emerald-500" rotulo="Online agora" valor={online} />
                <Indicador icone="fa-phone" rotulo="Setores com telefone" valor={todos.filter(n => n.ativo && n.telefone).length} />
            </div>

            <div className="flex flex-wrap items-center gap-2 mb-3">
                {[['estrutura', 'fa-sitemap', 'Estrutura'], ['telefones', 'fa-phone', 'Telefones']].map(([k, i, r]) => (
                    <button key={k} type="button" onClick={() => setAba(k)}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium ${aba === k ? 'bg-cyan-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'}`}>
                        <i className={`fas ${i} mr-1.5`} />{r}
                    </button>
                ))}
                <input type="search" value={busca} onChange={(e) => setBusca(e.target.value)} className="ds-input ml-auto max-w-sm"
                    placeholder="Buscar servidor, setor ou sigla…" aria-label="Buscar no organograma" />
                {gerencia && aba === 'estrutura' && (
                    <label className="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" checked={inativos} onChange={(e) => setInativos(e.target.checked)} className="rounded border-gray-300" />
                        Mostrar desativados
                    </label>
                )}
            </div>

            {aba === 'estrutura' ? (
                <Card>
                    {termo && visiveis.size === 0 && <p className="text-sm text-gray-400 py-6 text-center">Nada encontrado para “{busca}”.</p>}
                    <ul className="space-y-1">
                        {arvore.map(n => <No key={n.id} no={n} labels={labels} servidores={servidores} visiveis={visiveis} termo={termo}
                            inativos={inativos} gerencia={gerencia} onDesativar={setDesativando} />)}
                    </ul>
                </Card>
            ) : (
                <Telefones todos={todos.filter(n => n.ativo && (! visiveis || visiveis.has(n.id)))} />
            )}

            {gerencia && <Publicacao ug={ug} link={link} />}

            <DesativarSetorModal setor={desativando} onClose={() => setDesativando(null)}
                url={desativando ? `/organograma/${desativando.id}/desativar` : ''}
                opcoes={setoresAtivos(arvore)} servidores={desativando ? (servidores[desativando.id] || []).length : 0} />
        </AdminLayout>
    );
}

function achatar(arvore, ancestrais = [], caminho = []) {
    return arvore.flatMap(n => [
        { ...n, ancestrais, caminho: [...caminho, n.nome].join(' › ') },
        ...achatar(n.filhos, [...ancestrais, n.id], [...caminho, n.nome]),
    ]);
}

function Indicador({ icone, rotulo, valor }) {
    return (
        <div className="bg-white border border-gray-200 rounded-xl px-4 py-3">
            <p className="text-xs text-gray-500"><i className={`fas ${icone} mr-1.5`} />{rotulo}</p>
            <p className="text-2xl font-bold text-gray-800">{valor}</p>
        </div>
    );
}

function No({ no, labels, servidores, visiveis, termo, inativos, gerencia, onDesativar, depth = 0 }) {
    const [aberto, setAberto] = useState(depth < 1);
    const [verServidores, setVerServidores] = useState(false);
    if (! no.ativo && ! inativos) return null;
    if (visiveis && ! visiveis.has(no.id)) return null;

    const pessoas = servidores[no.id] || [];
    const onlineAqui = pessoas.filter(p => p.online).length;
    const filhos = no.filhos.filter(f => f.ativo || inativos);
    const expandido = termo ? true : aberto;
    const mostrarPessoas = verServidores || (termo && pessoas.some(p => sem(p.nome).includes(termo)));

    return (
        <li>
            <div className={`group flex flex-wrap items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 ${no.ativo ? '' : 'opacity-60'}`} style={{ marginLeft: depth * 20 }}>
                {filhos.length > 0 ? (
                    <button type="button" onClick={() => setAberto(! aberto)} className="w-5 h-5 rounded hover:bg-gray-200" aria-label={expandido ? 'Recolher' : 'Expandir'}>
                        <i className={`fas fa-chevron-${expandido ? 'down' : 'right'} text-[10px] text-gray-500`} />
                    </button>
                ) : <span className="w-5" />}
                <i className={`fas ${no.nivel === 1 ? 'fa-building' : 'fa-folder'} text-cyan-600`} />
                <span className="text-sm font-semibold text-gray-800">{no.nome}</span>
                {no.sigla && <span className="text-[10px] font-bold rounded bg-cyan-50 text-cyan-700 px-1.5 py-0.5">{no.sigla}</span>}
                <span className="text-[10px] text-gray-400">{labels[no.nivel] ?? 'Subsetor'}</span>
                {! no.ativo && <span className="text-[9px] font-bold rounded bg-gray-200 text-gray-600 px-1.5 py-0.5">DESATIVADO</span>}
                {no.responsavel && <span className="text-xs text-gray-500"><i className="fas fa-user-tie mr-1" />{no.responsavel.nome}</span>}
                {(no.telefone || no.ramal) && <span className="text-xs text-gray-500"><i className="fas fa-phone mr-1" />{[no.telefone, no.ramal && `ramal ${no.ramal}`].filter(Boolean).join(' · ')}</span>}
                <span className="ml-auto flex items-center gap-2">
                    {pessoas.length > 0 && (
                        <button type="button" onClick={() => setVerServidores(! verServidores)}
                            className="text-xs rounded-full border border-gray-200 px-2 py-0.5 text-gray-600 hover:bg-white">
                            <i className="fas fa-users mr-1" />{pessoas.length}
                            {onlineAqui > 0 && <span className="ml-1.5 text-emerald-600"><i className="fas fa-circle text-[7px] mr-0.5" />{onlineAqui}</span>}
                        </button>
                    )}
                    {gerencia && (no.ativo
                        ? <button type="button" onClick={() => onDesativar(no)} className="text-[11px] rounded px-2 py-0.5 text-amber-700 hover:bg-amber-50 opacity-0 group-hover:opacity-100 focus:opacity-100" title="Desativar setor"><i className="fas fa-pause" /></button>
                        : <button type="button" onClick={() => router.post(`/organograma/${no.id}/reativar`, {}, { preserveScroll: true })} className="text-[11px] rounded px-2 py-0.5 text-green-700 hover:bg-green-50" title="Reativar setor"><i className="fas fa-play" /></button>)}
                </span>
            </div>
            {mostrarPessoas && pessoas.length > 0 && (
                <div className="mb-2 overflow-x-auto" style={{ marginLeft: depth * 20 + 28 }}>
                    <table className="min-w-full text-xs">
                        <thead><tr className="text-left text-gray-500"><th className="py-1 pr-3 font-medium">Servidor</th><th className="pr-3 font-medium">Cargo</th><th className="pr-3 font-medium">Cadastro</th><th className="font-medium">Último acesso</th></tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {pessoas.map(p => (
                                <tr key={p.id} className={termo && sem(p.nome).includes(termo) ? 'bg-amber-50' : ''}>
                                    <td className="py-1.5 pr-3">
                                        <span className="flex items-center gap-2">
                                            <span className="relative">
                                                {p.foto_url ? <img src={p.foto_url} alt="" className="w-6 h-6 rounded-full object-cover" />
                                                    : <span className="w-6 h-6 rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 flex items-center justify-center">{p.nome.charAt(0)}</span>}
                                                <span className={`absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white ${p.online ? 'bg-emerald-500' : 'bg-gray-300'}`} title={p.online ? 'Online agora' : 'Offline'} />
                                            </span>
                                            <span className="text-gray-800">{p.nome}</span>
                                            {! p.principal && <span className="text-[9px] rounded bg-gray-100 text-gray-500 px-1">secundário</span>}
                                        </span>
                                    </td>
                                    <td className="pr-3 text-gray-600">{p.cargo || '-'}</td>
                                    <td className="pr-3 text-gray-600">{p.cadastro || '-'}</td>
                                    <td className={p.online ? 'text-emerald-600 font-medium' : 'text-gray-600'}>{p.online ? 'Online agora' : (p.ultimo_acesso || 'Nunca acessou')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {expandido && filhos.length > 0 && (
                <ul>
                    {filhos.map(f => <No key={f.id} no={f} labels={labels} servidores={servidores} visiveis={visiveis} termo={termo}
                        inativos={inativos} gerencia={gerencia} onDesativar={onDesativar} depth={depth + 1} />)}
                </ul>
            )}
        </li>
    );
}

/** Lista de telefones dos setores (TR 4.12.7), para consultar ou imprimir. */
function Telefones({ todos }) {
    const [semContato, setSemContato] = useState(false);
    const comContato = todos.filter(n => n.telefone || n.ramal || n.email);
    const lista = semContato ? todos : comContato;
    return (
        <Card>
            <div className="flex items-center justify-between gap-3 mb-2 print:hidden">
                <label className="flex items-center gap-1.5 text-xs text-gray-600">
                    <input type="checkbox" checked={semContato} onChange={(e) => setSemContato(e.target.checked)} className="rounded border-gray-300" />
                    Incluir setores sem telefone ({todos.length - comContato.length})
                </label>
                <Button size="sm" variant="secondary" icon="fas fa-print" onClick={() => window.print()}>Imprimir</Button>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead><tr className="text-left text-xs text-gray-500 border-b border-gray-200 whitespace-nowrap">
                        <th className="py-2 pr-3 font-medium">Setor</th><th className="pr-3 font-medium">Sigla</th><th className="pr-3 font-medium">Telefone</th>
                        <th className="pr-3 font-medium">Ramal</th><th className="pr-3 font-medium">E-mail</th><th className="font-medium">Responsável</th>
                    </tr></thead>
                    <tbody className="divide-y divide-gray-100">
                        {lista.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-gray-400">Nenhum setor com telefone cadastrado. O telefone é informado no cadastro do setor (Configurações › Unidades Gestoras › Organograma).</td></tr>}
                        {lista.map(n => (
                            <tr key={n.id}>
                                <td className="py-1.5 pr-3 text-gray-800">{n.caminho}</td>
                                <td className="pr-3 text-gray-600">{n.sigla || ''}</td>
                                <td className="pr-3 text-gray-800 whitespace-nowrap">{n.telefone || <span className="text-gray-300">—</span>}</td>
                                <td className="pr-3 text-gray-600">{n.ramal || ''}</td>
                                <td className="pr-3">{n.email ? <a href={`mailto:${n.email}`} className="text-cyan-700 hover:underline">{n.email}</a> : ''}</td>
                                <td className="text-gray-600">{n.responsavel?.nome || ''}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

/** Versão pública para o site da prefeitura (TR 4.12.8): liga/desliga, link e código do iframe. */
function Publicacao({ ug, link }) {
    const [copiado, setCopiado] = useState('');
    const iframe = link ? `<iframe src="${link}" width="100%" height="700" style="border:0" title="Organograma — ${ug.nome}"></iframe>` : '';
    const enviar = (dados) => router.post('/organograma/publicar', dados, { preserveScroll: true });
    const copiar = (texto, qual) => { navigator.clipboard?.writeText(texto); setCopiado(qual); setTimeout(() => setCopiado(''), 2000); };

    return (
        <Card title="Organograma no site da prefeitura" className="mt-4 print:hidden">
            <p className="text-sm text-gray-600 mb-3">
                A versão pública mostra só os setores ativos, o responsável e os contatos de cada um — sem a lista de servidores.
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <Button size="sm" variant={ug.organograma_publico ? 'secondary' : 'primary'} icon={`fas fa-${ug.organograma_publico ? 'eye-slash' : 'globe'}`}
                    onClick={() => enviar({ publico: ! ug.organograma_publico })}>
                    {ug.organograma_publico ? 'Desligar versão pública' : 'Ligar versão pública'}
                </Button>
                {ug.organograma_publico && link && (
                    <Button size="sm" variant="secondary" icon="fas fa-rotate" onClick={() => enviar({ publico: true, trocar: true })}>Trocar o link</Button>
                )}
            </div>
            {ug.organograma_publico && link && (
                <div className="mt-3 space-y-2">
                    <div>
                        <p className="text-xs font-medium text-gray-600 mb-1">Link</p>
                        <div className="flex gap-2">
                            <input readOnly value={link} className="ds-input font-mono text-xs" onFocus={(e) => e.target.select()} />
                            <Button size="sm" variant="secondary" icon="fas fa-copy" onClick={() => copiar(link, 'link')}>{copiado === 'link' ? 'Copiado' : 'Copiar'}</Button>
                            <a href={link} target="_blank" rel="noreferrer" className="ds-btn ds-btn-secondary text-xs whitespace-nowrap"><i className="fas fa-arrow-up-right-from-square mr-1" />Abrir</a>
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-medium text-gray-600 mb-1">Código para colar no site</p>
                        <div className="flex gap-2">
                            <textarea readOnly value={iframe} rows={2} className="ds-input font-mono text-xs" onFocus={(e) => e.target.select()} />
                            <Button size="sm" variant="secondary" icon="fas fa-copy" onClick={() => copiar(iframe, 'iframe')}>{copiado === 'iframe' ? 'Copiado' : 'Copiar'}</Button>
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}
