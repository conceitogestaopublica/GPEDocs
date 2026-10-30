/**
 * Mapa das demandas (TR 4.32.21, 4.33.9, 4.25.21–26): processos com localização em pinos ou mapa de
 * calor, coloridos pela situação ou pela faixa de tempo (em aberto até 20 / 20–30 / mais de 30 dias;
 * resolvidos em até 30 / mais de 30), com filtros por assunto, situação, setor, faixa e período.
 * Clique no pino abre assunto, situação, endereço e o link do processo.
 */
import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import MapaLeaflet, { escapar } from '../../../Components/MapaLeaflet';

const LEGENDA = [['#0e7490', 'Em andamento'], ['#7c3aed', 'Aguardando assinatura'], ['#16a34a', 'Concluído'], ['#64748b', 'Arquivado'], ['#dc2626', 'Cancelado']];
const SITUACAO = { aberto: 'Em andamento', aguardando_assinatura: 'Aguardando assinatura', concluido: 'Concluído', arquivado: 'Arquivado', cancelado: 'Cancelado' };

export default function Mapa({ pontos = [], filtros = {}, tipos = [], setores = [], faixas = [], cidade = '' }) {
    const [f, setF] = useState({ tipo: filtros.tipo || '', situacao: filtros.situacao || '', de: filtros.de || '', ate: filtros.ate || '',
        unidade_id: filtros.unidade_id || '', faixa: filtros.faixa || '' });
    const [modo, setModo] = useState('pinos');
    const [corPor, setCorPor] = useState(filtros.faixa ? 'faixa' : 'situacao');
    const filtrar = (e) => {
        e.preventDefault();
        router.get('/flow/mapa', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: false });
    };
    const rotuloFaixa = Object.fromEntries(faixas.map((x) => [x.chave, x.rotulo]));
    const marcadores = useMemo(() => pontos.map(p => ({
        lat: p.lat, lng: p.lng, cor: corPor === 'faixa' ? p.cor_faixa : p.cor,
        popup: `<b><a href="/processos/${p.id}">${escapar(p.numero)}</a></b><br/>${escapar(p.assunto)}<br/><span style="color:#555">${escapar(p.tipo || '')}</span>`
            + `<br/>Situação: <b>${escapar(SITUACAO[p.status] || p.status)}</b>` + (p.faixa ? ` — ${escapar(rotuloFaixa[p.faixa] || '')} (${p.dias} dia(s))` : '')
            + (p.endereco ? `<br/>${escapar(p.endereco)}` : '') + (p.nota ? `<br/>Avaliação: ${'★'.repeat(Math.round(p.nota))} (${p.nota})` : '')
            + `<br/><a href="/processos/${p.id}">Abrir o processo →</a>`,
    })), [pontos, corPor]);
    const contagem = faixas.map((x) => ({ ...x, n: pontos.filter((p) => p.faixa === x.chave).length }));
    const sel = 'ds-input';

    return (
        <AdminLayout>
            <Head title="Mapa das demandas" />
            <PageHeader title="Mapa das demandas" subtitle="Processos com o local marcado — pelo cidadão no portal ou pelo servidor." />
            <Card className="mb-4">
                <form onSubmit={filtrar} className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Assunto</label>
                        <select value={f.tipo} onChange={(e) => setF({ ...f, tipo: e.target.value })} className={sel}>
                            <option value="">Todos</option>
                            {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Situação</label>
                        <select value={f.situacao} onChange={(e) => setF({ ...f, situacao: e.target.value })} className={sel}>
                            <option value="">Todas</option><option value="abertos">Em aberto</option><option value="concluidos">Concluídos</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Setor</label>
                        <select value={f.unidade_id} onChange={(e) => setF({ ...f, unidade_id: e.target.value })} className={`${sel} max-w-[16rem]`}>
                            <option value="">Todos</option>
                            {setores.map(s => <option key={s.id} value={s.id}>{s.sigla ? `${s.sigla} — ${s.nome}` : s.nome}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Faixa de tempo</label>
                        <select value={f.faixa} onChange={(e) => setF({ ...f, faixa: e.target.value })} className={sel}>
                            <option value="">Todas</option>
                            {faixas.map(x => <option key={x.chave} value={x.chave}>{x.rotulo}</option>)}
                        </select>
                    </div>
                    <div><label className="block text-xs font-medium text-gray-600 mb-1">De</label><input type="date" value={f.de} onChange={(e) => setF({ ...f, de: e.target.value })} className={sel} /></div>
                    <div><label className="block text-xs font-medium text-gray-600 mb-1">Até</label><input type="date" value={f.ate} onChange={(e) => setF({ ...f, ate: e.target.value })} className={sel} /></div>
                    <Button type="submit" variant="secondary" icon="fas fa-filter">Filtrar</Button>
                </form>
            </Card>
            <Card>
                <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <div className="inline-flex overflow-hidden rounded-lg border border-gray-300">
                        {[['pinos', 'Pinos', 'fas fa-location-dot'], ['calor', 'Mapa de calor', 'fas fa-fire']].map(([k, r, ic]) => (
                            <button key={k} type="button" onClick={() => setModo(k)} className={`px-3 py-1.5 ${modo === k ? 'bg-cyan-700 text-white' : 'bg-white text-gray-600'}`}><i className={`${ic} mr-1`} />{r}</button>
                        ))}
                    </div>
                    {modo === 'pinos' && (
                        <div className="inline-flex overflow-hidden rounded-lg border border-gray-300">
                            {[['situacao', 'Cor pela situação'], ['faixa', 'Cor pelo tempo']].map(([k, r]) => (
                                <button key={k} type="button" onClick={() => setCorPor(k)} className={`px-3 py-1.5 ${corPor === k ? 'bg-cyan-700 text-white' : 'bg-white text-gray-600'}`}>{r}</button>
                            ))}
                        </div>
                    )}
                    <span className="ml-auto text-xs text-gray-600">{pontos.length} demanda(s) no mapa</span>
                </div>
                <MapaLeaflet pontos={marcadores} cidade={cidade} altura={560} calor={modo === 'calor'} />
                <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-600">
                    {modo === 'calor' ? <span>Quanto mais quente a cor, mais demandas naquela região.</span>
                        : corPor === 'faixa'
                            ? contagem.map((x) => <span key={x.chave} className="flex items-center gap-1.5"><span className="inline-block w-3 h-3 rounded-full" style={{ background: x.cor }} />{x.rotulo} ({x.n})</span>)
                            : LEGENDA.map(([c, r]) => <span key={c} className="flex items-center gap-1.5"><span className="inline-block w-3 h-3 rounded-full" style={{ background: c }} />{r}</span>)}
                </div>
            </Card>
        </AdminLayout>
    );
}
