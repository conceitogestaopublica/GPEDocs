/**
 * Página de Login — layout split estilo "Conta Atricon":
 *   ESQUERDA: painel de boas-vindas (gradiente claro, ícone da marca, destaques).
 *   DIREITA:  card branco com o formulário de acesso.
 *
 * Marca GPE Docs (azul/ciano + laranja). Mantém o contrato do form:
 * campos email/password/remember, POST /login, e "lembrar" guarda o e-mail
 * no localStorage para auto-preencher no próximo acesso.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const STORAGE_KEY = 'gpe_remember_email';

export default function Login({ flash = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPass, setShowPass] = useState(false);

    useEffect(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) setData({ email: saved, password: '', remember: true });
        } catch (_) { /* localStorage indisponível */ }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        try {
            if (data.remember && data.email) localStorage.setItem(STORAGE_KEY, data.email);
            else localStorage.removeItem(STORAGE_KEY);
        } catch (_) { /* sem localStorage */ }
        post('/login');
    };

    return (
        <>
            <Head title="Entrar - GPE Docs" />

            <div className="min-h-screen flex bg-white">
                {/* ═══ ESQUERDA — Boas-vindas ═══ */}
                <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden flex-col justify-center px-16 xl:px-24
                    bg-gradient-to-br from-cyan-50 via-white to-blue-50">
                    {/* blobs decorativos */}
                    <div className="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-cyan-200/30 blur-3xl" />
                    <div className="absolute -bottom-32 -right-10 w-96 h-96 rounded-full bg-blue-200/30 blur-3xl" />

                    <div className="relative">
                        {/* Logo GPE Docs — wordmark + caixa DENTRO da nuvem (destaque) */}
                        <div className="relative mb-10 select-none w-fit">
                            {/* halo de brilho (dá fundo p/ a nuvem branca aparecer) */}
                            <div className="absolute left-6 top-4 w-60 h-40 bg-cyan-300/60 blur-3xl rounded-full" />
                            <div className="relative inline-block">
                                <i className="fas fa-cloud text-[10rem] leading-none text-white
                                    drop-shadow-[0_14px_30px_rgba(6,132,206,0.45)]" />
                                {/* conteúdo dentro da nuvem: caixa + wordmark */}
                                <div className="absolute inset-0 flex flex-col items-center justify-center translate-y-[14%]">
                                    <i className="fas fa-box-archive text-blue-600 text-[2rem] drop-shadow-sm" />
                                    <div className="mt-0.5 text-2xl font-extrabold tracking-tight leading-none whitespace-nowrap">
                                        <span className="text-blue-700">GPE</span> <span className="text-orange-500">Docs</span>
                                    </div>
                                </div>
                                {/* fluxo de dados saindo da nuvem */}
                                <span className="absolute -bottom-1 left-[30%] w-1.5 h-1.5 rounded-full bg-cyan-400/70" />
                                <span className="absolute -bottom-3 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-cyan-400/50" />
                                <span className="absolute -bottom-1 left-[68%] w-1.5 h-1.5 rounded-full bg-cyan-400/70" />
                            </div>
                        </div>

                        <h1 className="text-4xl font-extrabold leading-tight text-slate-800">Bem-vindo de volta!</h1>
                        <p className="mt-3 text-lg text-slate-500 max-w-md">
                            Sua porta de entrada para a <strong className="text-slate-700">gestão documental municipal</strong>.
                        </p>

                        <div className="flex flex-wrap gap-3 mt-8">
                            {['Multi-entidade', 'Seguro', 'Em nuvem'].map((t) => (
                                <span key={t} className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/70 border border-slate-200 text-sm font-medium text-slate-600 shadow-sm">
                                    <span className="w-2 h-2 rounded-full bg-emerald-500" /> {t}
                                </span>
                            ))}
                        </div>
                    </div>

                    <p className="absolute bottom-6 left-16 xl:left-24 text-xs text-slate-400">
                        © {new Date().getFullYear()} Conceito Gestão Pública — Assessoria &amp; Tecnologia
                    </p>
                </div>

                {/* ═══ DIREITA — Formulário ═══ */}
                <div className="w-full lg:w-1/2 flex items-center justify-center px-6 sm:px-10 py-12">
                    <div className="w-full max-w-md">
                        {/* Logo + marca */}
                        <div className="flex items-center gap-3 mb-8">
                            <div className="w-12 h-12 rounded-xl shadow-md bg-gradient-to-br from-cyan-600 to-blue-700 text-white flex items-center justify-center">
                                <i className="fas fa-box-archive text-lg" />
                            </div>
                            <div className="text-xl font-bold text-slate-800">
                                GPE <span className="text-orange-500">Docs</span>
                            </div>
                        </div>

                        <h2 className="text-2xl font-bold text-slate-800">Entrar</h2>
                        <p className="text-sm text-slate-400 mt-1 mb-6">Faça login para acessar sua conta</p>

                        {flash?.success && (
                            <div className="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl flex items-center gap-2">
                                <i className="fas fa-check-circle" /> {flash.success}
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* E-mail */}
                            <div>
                                <label className="block text-sm font-medium text-slate-600 mb-1.5">E-mail</label>
                                <div className="relative">
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="Digite seu e-mail"
                                        autoFocus
                                        autoComplete="username"
                                        className={`w-full pl-4 pr-11 py-3 rounded-xl text-sm text-slate-800 placeholder-slate-400 border bg-slate-50/60 outline-none transition-all
                                            ${errors.email ? 'border-red-400 focus:ring-2 focus:ring-red-200' : 'border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100'}`}
                                    />
                                    <i className="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
                                </div>
                                {errors.email && <p className="mt-1.5 text-sm text-red-600 flex items-center gap-1.5"><i className="fas fa-exclamation-circle text-xs" /> {errors.email}</p>}
                            </div>

                            {/* Senha */}
                            <div>
                                <label className="block text-sm font-medium text-slate-600 mb-1.5">Senha</label>
                                <div className="relative">
                                    <input
                                        type={showPass ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Digite sua senha"
                                        autoComplete="current-password"
                                        className={`w-full pl-4 pr-11 py-3 rounded-xl text-sm text-slate-800 placeholder-slate-400 border bg-slate-50/60 outline-none transition-all
                                            ${errors.password ? 'border-red-400 focus:ring-2 focus:ring-red-200' : 'border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100'}`}
                                    />
                                    <button type="button" onClick={() => setShowPass(!showPass)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                                        <i className={`fas ${showPass ? 'fa-eye-slash' : 'fa-eye'} text-sm`} />
                                    </button>
                                </div>
                                {errors.password && <p className="mt-1.5 text-sm text-red-600">{errors.password}</p>}
                            </div>

                            {/* Lembrar + esqueci */}
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-600 select-none">
                                    <input type="checkbox" checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-200 cursor-pointer" />
                                    Lembrar-me
                                </label>
                                <Link href="/forgot-password" className="text-sm font-medium text-blue-600 hover:text-blue-700">
                                    Esqueceu a senha?
                                </Link>
                            </div>

                            {/* Botão */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-3 mt-1 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/30 transition-all disabled:opacity-60 disabled:cursor-not-allowed
                                    bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 flex items-center justify-center gap-2"
                            >
                                {processing ? (<><i className="fas fa-spinner fa-spin" /> Acessando...</>) : (<>Entrar <i className="fas fa-right-to-bracket" /></>)}
                            </button>
                        </form>

                        <p className="lg:hidden text-center text-[11px] text-slate-400 mt-8">
                            © {new Date().getFullYear()} Conceito Gestão Pública — Assessoria &amp; Tecnologia
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}