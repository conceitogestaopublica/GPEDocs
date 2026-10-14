/**
 * Definição da nova senha a partir do link enviado por e-mail.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import { CartaoAuth } from './EsqueciSenha';

export default function RedefinirSenha({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const enviar = (e) => {
        e.preventDefault();
        post('/reset-password');
    };

    const campo = 'w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400';

    return (
        <CartaoAuth titulo="Definir nova senha" subtitulo="A senha precisa ter pelo menos 8 caracteres.">
            <Head title="Nova senha - GPE Docs" />
            <form onSubmit={enviar} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">E-mail</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required className={campo} />
                    {errors.email && <p className="mt-1.5 text-sm text-red-600">{errors.email}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Nova senha</label>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required minLength={8} autoFocus className={campo} />
                    {errors.password && <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Confirme a nova senha</label>
                    <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required minLength={8} className={campo} />
                </div>
                <button type="submit" disabled={processing}
                    className="w-full py-3 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 disabled:opacity-60">
                    Salvar nova senha
                </button>
            </form>
            <p className="mt-6 text-center text-sm">
                <Link href="/login" className="text-blue-600 hover:text-blue-700 font-medium">Voltar para o login</Link>
            </p>
        </CartaoAuth>
    );
}
