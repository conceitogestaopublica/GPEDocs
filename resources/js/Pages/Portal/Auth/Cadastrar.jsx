/**
 * Portal — Cadastro de cidadao.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import PortalLayout from '../../../Layouts/PortalLayout';

export default function PortalCadastrar({ ug }) {
    const { data, setData, post, processing, errors } = useForm({
        nome: '',
        email: '',
        cpf: '',
        telefone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/cadastrar');
    };

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title="Cadastrar — Portal do Cidadao" />

            <div className="max-w-lg mx-auto bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                <div className="text-center mb-6">
                    <div className="inline-flex w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl items-center justify-center shadow-md mb-3">
                        <i className="fas fa-user-plus text-white text-2xl" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-800">Criar conta</h1>
                    <p className="text-sm text-gray-500">Cadastre-se para solicitar servicos online</p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Nome completo *</label>
                        <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)} required autoFocus
                            className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        {errors.nome && <p className="text-xs text-red-500 mt-1">{errors.nome}</p>}
                    </div>

                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">E-mail *</label>
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required
                            className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        {errors.email && <p className="text-xs text-red-500 mt-1">{errors.email}</p>}
                        <p className="text-[10px] text-gray-400 mt-1">Voce recebera notificacoes das suas solicitacoes neste e-mail.</p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">CPF</label>
                            <input type="text" value={data.cpf} onChange={(e) => setData('cpf', e.target.value)}
                                placeholder="000.000.000-00" maxLength={14}
                                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">Telefone</label>
                            <input type="text" value={data.telefone} onChange={(e) => setData('telefone', e.target.value)}
                                placeholder="(00) 00000-0000" maxLength={20}
                                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">Senha *</label>
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required minLength={6}
                                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                            {errors.password && <p className="text-xs text-red-500 mt-1">{errors.password}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">Confirme a senha *</label>
                            <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required
                                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        </div>
                    </div>

                    <button type="submit" disabled={processing}
                        className="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50 transition-colors mt-2">
                        {processing ? 'Cadastrando...' : 'Criar conta'}
                    </button>
                </form>

                <div className="text-center mt-6 pt-6 border-t border-gray-100">
                    <p className="text-sm text-gray-500">
                        Ja tem conta?{' '}
                        <Link href="/entrar" className="text-blue-600 font-semibold hover:underline">
                            Entrar
                        </Link>
                    </p>
                </div>
            </div>
        </PortalLayout>
    );
}
