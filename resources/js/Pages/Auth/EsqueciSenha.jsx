/**
 * Recuperação de senha — pede o e-mail e envia o link de redefinição.
 */
import { Head, Link, useForm } from '@inertiajs/react';

export default function EsqueciSenha({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const enviar = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <CartaoAuth titulo="Esqueceu a senha?" subtitulo="Informe o e-mail da sua conta. Enviaremos um link para definir uma nova senha.">
            <Head title="Recuperar senha - GPE Docs" />
            {status && (
                <div className="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    <i className="fas fa-check-circle mr-1" /> {status}
                </div>
            )}
            <form onSubmit={enviar} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">E-mail</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoFocus required
                        className="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                    {errors.email && <p className="mt-1.5 text-sm text-red-600">{errors.email}</p>}
                </div>
                <button type="submit" disabled={processing}
                    className="w-full py-3 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 disabled:opacity-60">
                    Enviar link
                </button>
            </form>
            <p className="mt-6 text-center text-sm">
                <Link href="/login" className="text-blue-600 hover:text-blue-700 font-medium">Voltar para o login</Link>
            </p>
        </CartaoAuth>
    );
}

export function CartaoAuth({ titulo, subtitulo, children }) {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-cyan-50 via-white to-blue-50 px-4">
            <div className="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
                <div className="mb-6">
                    <h1 className="text-xl font-bold text-slate-800">{titulo}</h1>
                    {subtitulo && <p className="text-sm text-slate-500 mt-1">{subtitulo}</p>}
                </div>
                {children}
            </div>
        </div>
    );
}
