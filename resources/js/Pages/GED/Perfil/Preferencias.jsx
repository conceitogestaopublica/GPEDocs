/**
 * Meu perfil › Preferências — editor de texto, abertura de anexos e quais
 * avisos chegam também por e-mail e SMS, com a abrangência de cada canal (TR 4.7.16–4.7.19, 4.11.4–4.11.6).
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

export default function Preferencias({ preferencias, abrangencias = {}, emails, celular = null }) {
    const { data, setData, post, processing, isDirty } = useForm(preferencias);
    const canal = (c, v) => setData('canais', { ...data.canais, [c]: v });

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
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-bell mr-2 text-cyan-600" />Avisos fora do sistema</p>
                    <p className="text-xs text-gray-500 mt-1 mb-3">
                        Todos os avisos continuam no sino. Escolha o que também chega por e-mail
                        {emails.length ? <b> ({emails.join(' e ')})</b> : ''} e por SMS{celular ? <b> ({celular})</b> : ''}.
                        "Direcionado a mim" é o que vem em seu nome; "do meu setor" inclui o que chega para o setor inteiro.
                    </p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead><tr className="text-xs text-gray-500 text-left">
                                <th className="py-1 pr-3 font-medium">Canal</th>
                                {Object.values(abrangencias).map((r) => <th key={r} className="py-1 px-2 font-medium text-center">{r}</th>)}
                            </tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {[['email', 'fa-envelope', 'E-mail'], ['sms', 'fa-mobile-screen', 'SMS']].map(([c, ic, rot]) => (
                                    <tr key={c}>
                                        <td className="py-2 pr-3 text-gray-800"><i className={`fas ${ic} mr-2 text-gray-400`} />{rot}
                                            {c === 'sms' && ! celular && <span className="block text-[11px] text-amber-700">Cadastre o celular em Meus dados para receber SMS.</span>}</td>
                                        {Object.keys(abrangencias).map((v) => (
                                            <td key={v} className="text-center">
                                                <input type="radio" name={`canal-${c}`} aria-label={`${rot}: ${abrangencias[v]}`} checked={data.canais[c] === v} onChange={() => canal(c, v)} />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-3 space-y-1">
                        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" checked={!! data.email_mencao} onChange={(e) => setData('email_mencao', e.target.checked)} className="rounded border-gray-300 text-cyan-600" />
                            E-mail quando alguém me mencionar (@) num documento
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" checked={!! data.email_assinatura} onChange={(e) => setData('email_assinatura', e.target.checked)} className="rounded border-gray-300 text-cyan-600" />
                            E-mail quando pedirem a minha assinatura
                        </label>
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" loading={processing} disabled={!isDirty} icon="fas fa-save">Salvar preferências</Button>
                </div>
            </form>
        </AdminLayout>
    );
}
