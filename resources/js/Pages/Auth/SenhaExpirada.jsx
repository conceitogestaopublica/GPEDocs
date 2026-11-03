/**
 * Senha vencida (validade da política de senha) ou provisória (definida pelo administrador): a pessoa
 * acabou de entrar com ela e cria a nova antes de seguir.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import { CartaoAuth } from './EsqueciSenha';

export default function SenhaExpirada({ nome, provisoria = false, regra }) {
    const { data, setData, post, processing, errors } = useForm({ password: '', password_confirmation: '' });
    const enviar = (e) => { e.preventDefault(); post('/senha/expirada'); };
    const campo = 'w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400';

    return (
        <CartaoAuth titulo={provisoria ? 'Crie a sua senha' : 'Sua senha venceu'}
            subtitulo={`${nome ? nome.split(' ')[0] + ', ' : ''}${provisoria ? 'a senha que você recebeu é provisória' : 'por segurança, a senha precisa ser trocada de tempos em tempos'}. Crie uma nova para continuar.`}>
            <Head title="Nova senha - GPE Docs" />
            <form onSubmit={enviar} className="space-y-4">
                <p className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">{regra}</p>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Nova senha</label>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required autoFocus className={campo} />
                    {errors.password && <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Confirme a nova senha</label>
                    <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required className={campo} />
                </div>
                <button type="submit" disabled={processing}
                    className="w-full py-3 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 disabled:opacity-60">
                    Salvar e entrar
                </button>
            </form>
            <p className="mt-6 text-center text-sm">
                <Link href="/login" className="text-blue-600 hover:text-blue-700 font-medium">Voltar para o login</Link>
            </p>
        </CartaoAuth>
    );
}
