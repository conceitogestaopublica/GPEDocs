/**
 * Portal — Login do cidadao.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import PortalLayout from '../../../Layouts/PortalLayout';

export default function PortalLogin({ ug }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/entrar');
    };

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title="Entrar — Portal do Cidadao" />

            <div className="max-w-md mx-auto bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                <div className="text-center mb-6">
                    <div className="inline-flex w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl items-center justify-center shadow-md mb-3">
                        <i className="fas fa-user text-white text-2xl" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-800">Entrar</h1>
                    <p className="text-sm text-gray-500">Acesse sua conta para solicitar servicos</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">E-mail</label>
                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                            autoFocus required
                            className="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        {errors.email && <p className="text-xs text-red-500 mt-1">{errors.email}</p>}
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Senha</label>
                        <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                            required
                            className="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                        {errors.password && <p className="text-xs text-red-500 mt-1">{errors.password}</p>}
                    </div>
                    <button type="submit" disabled={processing}
                        className="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50 transition-colors">
                        {processing ? 'Entrando...' : 'Entrar'}
                    </button>
                </form>

                <div className="text-center mt-6 pt-6 border-t border-gray-100">
                    <p className="text-sm text-gray-500">
                        Ainda nao tem conta?{' '}
                        <Link href="/cadastrar" className="text-blue-600 font-semibold hover:underline">
                            Cadastre-se
                        </Link>
                    </p>
                </div>
            </div>
        </PortalLayout>
    );
}
