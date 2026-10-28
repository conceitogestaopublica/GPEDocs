/**
 * Meu perfil › Preferências — editor de texto, abertura de anexos e quais
 * avisos chegam também por e-mail (TR 4.7.16, 4.7.17, 4.7.19).
 */
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Button from '../../../Components/Button';

function Opcao({ nome, valor, atual, onChange, titulo, descricao }) {
    const ativo = atual === valor;
    return (
        <label className={`flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer ${ativo ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200 hover:bg-gray-50'}`}>
            <input type="radio" name={nome} checked={ativo} onChange={() => onChange(valor)} className="mt-1" />
            <span><span className="block text-sm font-medium text-gray-800">{titulo}</span>
                <span className="block text-xs text-gray-500">{descricao}</span></span>
        </label>
    );
}

export default function Preferencias({ preferencias, avisos, emails }) {
    const { data, setData, post, processing, isDirty } = useForm(preferencias);
    const alternarAviso = (g) => setData('avisos', data.avisos.includes(g) ? data.avisos.filter((x) => x !== g) : [...data.avisos, g]);

    return (
        <AdminLayout>
            <Head title="Preferências" />
            <PageHeader title="Meu perfil" subtitle="Como o sistema funciona para você" />
            <PerfilAbas />

            <form onSubmit={(e) => { e.preventDefault(); post('/perfil/preferencias', { preserveScroll: true }); }} className="space-y-4">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="ds-card p-5 space-y-3">
                        <p className="text-sm font-semibold text-gray-800"><i className="fas fa-pen-nib mr-2 text-cyan-600" />Editor de texto</p>
                        <Opcao nome="editor" valor="avancado" atual={data.editor} onChange={(v) => setData('editor', v)}
                            titulo="Avançado" descricao="Negrito, listas, alinhamento e tabelas (ofícios e modelos)." />
                        <Opcao nome="editor" valor="basico" atual={data.editor} onChange={(v) => setData('editor', v)}
                            titulo="Básico" descricao="Só texto, sem formatação — mais simples e rápido." />
                    </div>
                    <div className="ds-card p-5 space-y-3">
                        <p className="text-sm font-semibold text-gray-800"><i className="fas fa-paperclip mr-2 text-cyan-600" />Ao abrir um anexo</p>
                        <Opcao nome="anexos" valor="visualizar" atual={data.anexos} onChange={(v) => setData('anexos', v)}
                            titulo="Visualizar no navegador" descricao="PDF e imagens abrem numa nova aba, sem baixar." />
                        <Opcao nome="anexos" valor="baixar" atual={data.anexos} onChange={(v) => setData('anexos', v)}
                            titulo="Baixar o arquivo" descricao="O arquivo vai direto para a pasta de downloads." />
                    </div>
                </div>

                <div className="ds-card p-5">
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-envelope mr-2 text-cyan-600" />Avisos também por e-mail</p>
                    <p className="text-xs text-gray-500 mt-1 mb-3">
                        Os avisos continuam aparecendo no sino. Marque os que também devem chegar em
                        {emails.length ? <b> {emails.join(' e ')}</b> : ' seu e-mail'}.
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        {Object.entries(avisos).map(([g, rotulo]) => (
                            <label key={g} className="flex items-center gap-2 text-sm text-gray-700 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" checked={data.avisos.includes(g)} onChange={() => alternarAviso(g)} className="rounded border-gray-300 text-cyan-600" />
                                {rotulo}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" loading={processing} disabled={!isDirty} icon="fas fa-save">Salvar preferências</Button>
                </div>
            </form>
        </AdminLayout>
    );
}
