/**
 * Tela de Login — GED
 */
import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Login" />
            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-indigo-50">
                <div className="w-full max-w-md">
                    {/* Logo */}
                    <div className="text-center mb-8">
                        <div className="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 mx-auto mb-4">
                            <i className="fas fa-archive text-white text-2xl" />
                        </div>
                        <h1 className="text-2xl font-bold text-gray-800">GED</h1>
                        <p className="text-sm text-gray-500 mt-1">Gestao Eletronica de Documentos</p>
                    </div>

                    {/* Form */}
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
                        <h2 className="text-lg font-semibold text-gray-800 mb-6">Entrar no sistema</h2>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="ds-input"
                                    placeholder="seu@email.com"
                                    autoFocus
                                />
                                {errors.email && <p className="mt-1 text-xs text-red-600"><i className="fas fa-exclamation-circle mr-1" />{errors.email}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="ds-input"
                                    placeholder="********"
                                />
                                {errors.password && <p className="mt-1 text-xs text-red-600"><i className="fas fa-exclamation-circle mr-1" />{errors.password}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="ds-btn ds-btn-primary w-full justify-center text-base"
                            >
                                {processing ? (
                                    <><i className="fas fa-spinner fa-spin mr-2" />Entrando...</>
                                ) : (
                                    <><i className="fas fa-sign-in-alt mr-2" />Entrar</>
                                )}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
