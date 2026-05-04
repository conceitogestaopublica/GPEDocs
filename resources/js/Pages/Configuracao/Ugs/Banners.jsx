/**
 * Banners do Portal Cidadao da UG — gerencia carrossel.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import Card from '../../../Components/Card';

export default function Banners({ ug, banners }) {
    const [showForm, setShowForm] = useState(false);
    const [editBanner, setEditBanner] = useState(null);

    const openCreate = () => { setEditBanner(null); setShowForm(true); };
    const openEdit = (b) => { setEditBanner(b); setShowForm(true); };

    const move = (banner, direcao) => {
        router.post(`/configuracoes/ugs/${ug.id}/banners/${banner.id}/move/${direcao}`, {}, { preserveScroll: true });
    };
    const excluir = (banner) => {
        if (! confirm('Excluir este banner? A imagem sera removida.')) return;
        router.delete(`/configuracoes/ugs/${ug.id}/banners/${banner.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title={`Banners — ${ug.nome}`} />

            <PageHeader
                title="Banners do Portal Cidadao"
                subtitle={`Carrossel exibido na home de ${ug.nome}`}
            >
                <Link href={`/configuracoes/ugs/${ug.id}/edit`}
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-semibold">
                    <i className="fas fa-arrow-left text-xs" /> Voltar para UG
                </Link>
                <Button icon="fas fa-plus" onClick={openCreate}>
                    Adicionar banner
                </Button>
            </PageHeader>

            {ug.portal_slug && (
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800 mb-4">
                    <i className="fas fa-info-circle mr-1" />
                    Portal publico: <strong>{ug.portal_slug}.gpedocs.com.br</strong> (em dev: <strong>{ug.portal_slug}.lvh.me:8000</strong>)
                </div>
            )}

            {banners.length === 0 ? (
                <Card>
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-images text-4xl mb-3 block" />
                        <p className="text-sm">Nenhum banner cadastrado ainda.</p>
                        <Button icon="fas fa-plus" onClick={openCreate} className="mt-4">Adicionar o primeiro</Button>
                    </div>
                </Card>
            ) : (
                <div className="space-y-3">
                    {banners.map((b, i) => (
                        <Card key={b.id} padding={false}>
                            <div className="flex items-stretch">
                                <div className="w-48 shrink-0 bg-gray-100 overflow-hidden">
                                    <img src={`/configuracoes/ugs/${ug.id}/banners/${b.id}/imagem`} alt={b.titulo || `Banner ${i + 1}`}
                                        className="w-full h-full object-cover" />
                                </div>
                                <div className="flex-1 p-4 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold ${b.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {b.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                        <span className="text-[10px] uppercase tracking-wider text-gray-400">Ordem #{b.ordem}</span>
                                    </div>
                                    <h3 className="text-base font-bold text-gray-800">{b.titulo || <span className="text-gray-400 italic">(sem titulo)</span>}</h3>
                                    {b.subtitulo && <p className="text-xs text-gray-600 mt-0.5">{b.subtitulo}</p>}
                                    {b.link_url && (
                                        <p className="text-[11px] text-blue-600 mt-1 truncate">
                                            <i className="fas fa-link mr-1" />{b.link_label || 'Saiba mais'} → {b.link_url}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-1 px-3 border-l border-gray-100">
                                    <div className="flex flex-col gap-1">
                                        <button onClick={() => move(b, 'cima')} disabled={i === 0}
                                            className="w-8 h-8 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-700 disabled:opacity-30" title="Mover pra cima">
                                            <i className="fas fa-chevron-up text-xs" />
                                        </button>
                                        <button onClick={() => move(b, 'baixo')} disabled={i === banners.length - 1}
                                            className="w-8 h-8 rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-700 disabled:opacity-30" title="Mover pra baixo">
                                            <i className="fas fa-chevron-down text-xs" />
                                        </button>
                                    </div>
                                    <button onClick={() => openEdit(b)}
                                        className="w-9 h-9 rounded-lg hover:bg-blue-50 text-gray-400 hover:text-blue-600" title="Editar">
                                        <i className="fas fa-pen text-xs" />
                                    </button>
                                    <button onClick={() => excluir(b)}
                                        className="w-9 h-9 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600" title="Excluir">
                                        <i className="fas fa-trash text-xs" />
                                    </button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {showForm && (
                <BannerForm
                    ugId={ug.id}
                    banner={editBanner}
                    onClose={() => setShowForm(false)}
                />
            )}
        </AdminLayout>
    );
}

function BannerForm({ ugId, banner, onClose }) {
    const isEdit = !!banner;
    const { data, setData, processing, errors } = useForm({
        imagem: null,
        titulo: banner?.titulo || '',
        subtitulo: banner?.subtitulo || '',
        link_url: banner?.link_url || '',
        link_label: banner?.link_label || '',
        ativo: banner?.ativo ?? true,
    });
    const [preview, setPreview] = useState(null);

    const handleImagem = (e) => {
        const file = e.target.files?.[0] ?? null;
        setData('imagem', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = ev => setPreview(ev.target.result);
            reader.readAsDataURL(file);
        } else {
            setPreview(null);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        const fd = new FormData();
        if (data.imagem instanceof File) fd.append('imagem', data.imagem);
        ['titulo', 'subtitulo', 'link_url', 'link_label'].forEach(k => fd.append(k, data[k] || ''));
        fd.append('ativo', data.ativo ? '1' : '0');

        const url = isEdit
            ? `/configuracoes/ugs/${ugId}/banners/${banner.id}`
            : `/configuracoes/ugs/${ugId}/banners`;
        if (isEdit) fd.append('_method', 'PUT');

        router.post(url, fd, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show onClose={onClose} title={isEdit ? 'Editar banner' : 'Adicionar banner'} maxWidth="2xl">
            <form onSubmit={submit} className="space-y-4">
                <div className="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 overflow-hidden" style={{ aspectRatio: '5 / 1' }}>
                    {preview ? (
                        <img src={preview} alt="Preview" className="w-full h-full object-cover" />
                    ) : isEdit ? (
                        <img src={`/configuracoes/ugs/${ugId}/banners/${banner.id}/imagem`} alt="Atual" className="w-full h-full object-cover" />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center text-gray-300">
                            <div className="text-center">
                                <i className="fas fa-image text-4xl mb-2" />
                                <p className="text-xs">Selecione uma imagem</p>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">
                        Imagem {isEdit ? '(opcional, mantem a atual se vazio)' : '*'} (PNG/JPG, ate 10MB — recomendado 1600x320px)
                    </label>
                    <input type="file" accept="image/png,image/jpeg,image/jpg" onChange={handleImagem}
                        className="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    {errors.imagem && <p className="text-xs text-red-500 mt-1">{errors.imagem}</p>}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Titulo (opcional)</label>
                        <input type="text" value={data.titulo} onChange={(e) => setData('titulo', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" maxLength={200}
                            placeholder="Ex: Inauguracao do novo CRAS" />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Subtitulo (opcional)</label>
                        <input type="text" value={data.subtitulo} onChange={(e) => setData('subtitulo', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
                            placeholder="Ex: Atendimento comeca dia 12" />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">URL do botao (opcional)</label>
                        <input type="url" value={data.link_url} onChange={(e) => setData('link_url', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" maxLength={500}
                            placeholder="https://..." />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Texto do botao (opcional)</label>
                        <input type="text" value={data.link_label} onChange={(e) => setData('link_label', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" maxLength={60}
                            placeholder="Ex: Saiba mais" />
                    </div>
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.ativo} onChange={(e) => setData('ativo', e.target.checked)} />
                    <span className="font-medium">Banner ativo (aparece no carrossel do portal)</span>
                </label>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing}
                        className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                        {isEdit ? 'Salvar' : 'Adicionar'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
