/**
 * Segunda etapa da entrada: código de 6 dígitos do aplicativo autenticador
 * ou um código de recuperação.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CartaoAuth } from './EsqueciSenha';

export default function DoisFatores({ minutos = 5 }) {
    const [recuperacao, setRecuperacao] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ codigo: '' });

    const enviar = (e) => {
        e.preventDefault();
        post('/login/verificacao');
    };

    return (
        <CartaoAuth titulo="Verificação em duas etapas"
            subtitulo={recuperacao
                ? 'Digite um dos códigos de recuperação que você guardou ao ativar a verificação. Cada código vale uma vez.'
                : 'Abra o aplicativo autenticador no celular e digite o código de 6 dígitos do GPE Docs.'}>
            <Head title="Verificação - GPE Docs" />
            <form onSubmit={enviar} className="space-y-4">
                <div>
                    <input type="text" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} autoFocus
                        inputMode={recuperacao ? 'text' : 'numeric'} autoComplete="one-time-code"
                        maxLength={recuperacao ? 9 : 6} placeholder={recuperacao ? 'xxxx-xxxx' : '000000'}
                        className="w-full px-4 py-3 rounded-xl border border-slate-200 text-center text-2xl tracking-[0.4em] font-mono focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                    {errors.codigo && <p className="mt-1.5 text-sm text-red-600 text-center">{errors.codigo}</p>}
                </div>
                <button type="submit" disabled={processing || !data.codigo}
                    className="w-full py-3 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 disabled:opacity-60">
                    {processing ? <><i className="fas fa-spinner fa-spin mr-1" /> Conferindo...</> : 'Entrar'}
                </button>
            </form>
            <div className="mt-6 flex items-center justify-between text-sm">
                <button type="button" onClick={() => { setRecuperacao(!recuperacao); setData('codigo', ''); }}
                    className="text-blue-600 hover:text-blue-700 font-medium">
                    {recuperacao ? 'Usar o código do aplicativo' : 'Perdi o celular — usar código de recuperação'}
                </button>
                <button type="button" onClick={() => router.post('/login/verificacao/cancelar')}
                    className="text-slate-500 hover:text-slate-700">Voltar</button>
            </div>
            <p className="mt-4 text-[11px] text-slate-400 text-center">Você tem {minutos} minutos para informar o código.</p>
        </CartaoAuth>
    );
}
