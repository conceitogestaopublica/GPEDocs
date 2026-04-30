/**
 * Cadastro de Unidades Gestoras (lista)
 * Botoes "Nova UG" e "Editar" navegam para tela dedicada de cadastro.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';

export default function UgsIndex({ ugs }) {
    const [busca, setBusca] = useState('');

    const filtrar = (lista) => {
        if (! busca.trim()) return lista;
        const t = busca.toLowerCase();
        return lista.filter(u =>
            u.nome.toLowerCase().includes(t) ||
            (u.codigo || '').toLowerCase().includes(t) ||
            (u.cnpj || '').toLowerCase().includes(t)
        );
    };

    const ativas    = filtrar(ugs.filter(u => u.ativo));
    const inativas  = filtrar(ugs.filter(u => ! u.ativo));

    return (
        <AdminLayout>
            <Head title="Unidades Gestoras" />
            <PageHeader title="Unidades Gestoras" subtitle="Cadastro de UGs com configuracao de organograma proprio">
                <Link href="/configuracoes/ugs/create"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">
                    <i className="fas fa-plus" />
                    Nova UG
                </Link>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-3 flex items-center gap-2">
                <div className="relative flex-1">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                    <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                        placeholder="Filtrar por codigo, nome ou CNPJ..."
                        className="ds-input pl-9" />
                </div>
                <span className="text-xs text-gray-400">{ugs.length} UG(s) cadastrada(s)</span>
            </div>

            {ugs.length === 0 ? (
                <Card>
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-building text-4xl mb-3 block" />
                        <p className="text-sm">Nenhuma UG cadastrada</p>
                        <p className="text-xs mt-1">Clique em "Nova UG" para comecar.</p>
                    </div>
                </Card>
            ) : (
                <>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                        {ativas.map(ug => <UgCard key={ug.id} ug={ug} />)}
                    </div>
                    {inativas.length > 0 && (
                        <details className="mb-4">
                            <summary className="text-xs text-gray-500 cursor-pointer mb-2">
                                {inativas.length} UG(s) inativa(s)
                            </summary>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                {inativas.map(ug => <UgCard key={ug.id} ug={ug} />)}
                            </div>
                        </details>
                    )}
                </>
            )}
        </AdminLayout>
    );
}

function UgCard({ ug }) {
    const { post, processing } = useForm();

    const toggleAtivo = () => post(`/configuracoes/ugs/${ug.id}/toggle-ativo`);

    return (
        <div className={`bg-white rounded-2xl border p-4 ${ug.ativo ? 'border-gray-100' : 'border-gray-200 opacity-60'}`}>
            <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-2">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${ug.ativo ? 'bg-indigo-100' : 'bg-gray-100'}`}>
                        <i className={`fas fa-building ${ug.ativo ? 'text-indigo-600' : 'text-gray-400'} text-sm`} />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800">{ug.nome}</p>
                        <p className="text-[11px] text-gray-500 font-mono">{ug.codigo}</p>
                    </div>
                </div>
                <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${
                    ug.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                }`}>
                    {ug.ativo ? 'Ativa' : 'Inativa'}
                </span>
            </div>

            <div className="space-y-1 text-[11px] text-gray-600 mb-3">
                {ug.cnpj && <Linha label="CNPJ" valor={ug.cnpj} />}
                {ug.cidade && <Linha label="Cidade" valor={`${ug.cidade}${ug.uf ? '/' + ug.uf : ''}`} />}
                <Linha label="Niveis" valor={`${ug.nivel_1_label} > ${ug.nivel_2_label} > ${ug.nivel_3_label}`} />
                <Linha label="Organograma" valor={`${ug.organograma_count} unidade(s)`} />
                <Linha label="Usuarios" valor={`${ug.usuarios_count} vinculado(s)`} />
            </div>

            <div className="flex justify-end gap-1 pt-2 border-t border-gray-100">
                <Link href={`/configuracoes/ugs/${ug.id}/organograma`}
                    className="text-[11px] px-3 py-1 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors">
                    <i className="fas fa-sitemap mr-1" />
                    Organograma
                </Link>
                <Link href={`/configuracoes/ugs/${ug.id}/edit`}
                    className="text-[11px] px-3 py-1 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                    <i className="fas fa-edit mr-1" />
                    Editar
                </Link>
                <button onClick={toggleAtivo} disabled={processing}
                    className={`text-[11px] px-3 py-1 rounded-lg transition-colors ${
                        ug.ativo
                            ? 'text-amber-600 hover:bg-amber-50'
                            : 'text-green-600 hover:bg-green-50'
                    }`}>
                    <i className={`fas fa-${ug.ativo ? 'pause' : 'play'} mr-1`} />
                    {ug.ativo ? 'Inativar' : 'Reativar'}
                </button>
            </div>
        </div>
    );
}

function Linha({ label, valor }) {
    return (
        <div className="flex items-start gap-2">
            <span className="text-gray-400 min-w-[80px] uppercase tracking-wide text-[9px] mt-0.5">{label}</span>
            <span className="text-gray-800 break-all flex-1">{valor || '-'}</span>
        </div>
    );
}
