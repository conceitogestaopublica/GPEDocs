/**
 * Textos do portal (TR 4.23.1, 4.23.5): política de privacidade e acesso à informação. O portal
 * e o app mostram o texto; com um endereço preenchido, também o link para a página completa.
 */
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

export default function PortalTextos({ textos = [] }) {
    return (
        <AdminLayout>
            <Head title="Textos do portal" />
            <PageHeader title="Textos do portal" subtitle="O que o cidadão lê no portal e no app sobre privacidade e acesso à informação" />
            <div className="space-y-6">
                {textos.map((t) => <Texto key={t.chave} texto={t} />)}
            </div>
        </AdminLayout>
    );
}

function Texto({ texto }) {
    const form = useForm({ titulo: texto.titulo, texto: texto.texto || '', url: texto.url || '' });
    const salvar = (e) => {
        e.preventDefault();
        form.put(`/configuracoes/portal-textos/${texto.chave}`, { preserveScroll: true });
    };

    return (
        <Card title={texto.titulo}>
            <form onSubmit={salvar} className="space-y-3">
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Título</label>
                    <input value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} className="ds-input" maxLength={200} />
                    {form.errors.titulo && <p className="mt-1 text-xs text-red-600">{form.errors.titulo}</p>}
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Texto</label>
                    <textarea value={form.data.texto} onChange={(e) => form.setData('texto', e.target.value)} rows={7} className="ds-input" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Endereço da página completa (opcional)</label>
                    <input type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} className="ds-input" placeholder="https://www.prefeitura.gov.br/privacidade" />
                    {form.errors.url && <p className="mt-1 text-xs text-red-600">{form.errors.url}</p>}
                </div>
                <div className="flex items-center justify-between">
                    <span className="text-[11px] text-gray-400">{texto.atualizado_em ? `Atualizado em ${new Date(texto.atualizado_em + 'Z').toLocaleString('pt-BR')}` : ''}</span>
                    <Button type="submit" icon="fas fa-save" loading={form.processing} disabled={! form.isDirty}>Salvar</Button>
                </div>
            </form>
        </Card>
    );
}
