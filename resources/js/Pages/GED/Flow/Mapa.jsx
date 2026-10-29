/**
 * Mapa das demandas (TR 4.32.21, 4.33.9): processos com localização, coloridos pela
 * situação, com filtros por assunto, situação e período. Clique no pino abre o resumo.
 */
import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import MapaLeaflet, { escapar } from '../../../Components/MapaLeaflet';

const LEGENDA = [['#0e7490', 'Em andamento'], ['#7c3aed', 'Aguardando assinatura'], ['#16a34a', 'Concluído'], ['#64748b', 'Arquivado'], ['#dc2626', 'Cancelado']];

export default function Mapa({ pontos = [], filtros = {}, tipos = [], cidade = '' }) {
    const [f, setF] = useState({ tipo: filtros.tipo || '', situacao: filtros.situacao || '', de: filtros.de || '', ate: filtros.ate || '' });
    const filtrar = (e) => {
        e.preventDefault();
        router.get('/flow/mapa', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: false });
    };
    const marcadores = useMemo(() => pontos.map(p => ({
        lat: p.lat, lng: p.lng, cor: p.cor,
        popup: `<b><a href="/processos/${p.id}">${escapar(p.numero)}</a></b><br/>${escapar(p.assunto)}<br/><span style="color:#555">${escapar(p.tipo || '')}</span>`
            + (p.endereco ? `<br/>${escapar(p.endereco)}` : '') + (p.nota ? `<br/>Avaliação: ${'★'.repeat(Math.round(p.nota))} (${p.nota})` : ''),
    })), [pontos]);

    return (
        <AdminLayout>
            <Head title="Mapa das demandas" />
            <PageHeader title="Mapa das demandas" subtitle="Processos com o local marcado — pelo cidadão no portal ou pelo servidor." />
            <Card className="mb-4">
                <form onSubmit={filtrar} className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Assunto</label>
                        <select value={f.tipo} onChange={(e) => setF({ ...f, tipo: e.target.value })} className="ds-input">
                            <option value="">Todos</option>
                            {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Situação</label>
                        <select value={f.situacao} onChange={(e) => setF({ ...f, situacao: e.target.value })} className="ds-input">
                            <option value="">Todas</option><option value="abertos">Em aberto</option><option value="concluidos">Concluídos</option>
                        </select>
                    </div>
                    <div><label className="block text-xs font-medium text-gray-600 mb-1">De</label><input type="date" value={f.de} onChange={(e) => setF({ ...f, de: e.target.value })} className="ds-input" /></div>
                    <div><label className="block text-xs font-medium text-gray-600 mb-1">Até</label><input type="date" value={f.ate} onChange={(e) => setF({ ...f, ate: e.target.value })} className="ds-input" /></div>
                    <Button type="submit" variant="secondary" icon="fas fa-filter">Filtrar</Button>
                </form>
            </Card>
            <Card>
                <MapaLeaflet pontos={marcadores} cidade={cidade} altura={560} />
                <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-600">
                    {LEGENDA.map(([c, r]) => <span key={c} className="flex items-center gap-1.5"><span className="inline-block w-3 h-3 rounded-full" style={{ background: c }} />{r}</span>)}
                    <span className="ml-auto">{pontos.length} demanda(s) no mapa</span>
                </div>
            </Card>
        </AdminLayout>
    );
}
