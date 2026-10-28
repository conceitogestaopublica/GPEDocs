/**
 * Página de Login — layout split estilo "Conta Atricon":
 *   ESQUERDA: painel de boas-vindas (gradiente claro, ícone da marca, destaques).
 *   DIREITA:  card branco com o formulário de acesso.
 *
 * Marca GPE Docs (azul/ciano + laranja). Mantém o contrato do form:
 * campos login (e-mail OU CPF)/password/remember, POST /login, e "lembrar" guarda
 * o login no localStorage para auto-preencher no próximo acesso.
 */
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Captcha from '../../Components/Captcha';
import EntrarComCertificado from '../../Components/EntrarComCertificado';
import { useState, useEffect } from 'react';

const STORAGE_KEY = 'gpe_remember_email';

export default function Login({ flash = {}, exige_captcha = false, provedores = [] }) {
    const erroEntrada = usePage().props.errors?.login;
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: false,
        captcha: '',
    });
    const [showPass, setShowPass] = useState(false);
    const [comCertificado, setComCertificado] = useState(false);

    useEffect(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) setData({ login: saved, password: '', remember: true });
        } catch (_) { /* localStorage indisponível */ }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        try {
            if (data.remember && data.login) localStorage.setItem(STORAGE_KEY, data.login);
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
                        {/* Mesmo arquivo de logo do GPE Cloud usado no gpe2 e no tributário — a
                            nuvem desenhada à mão divergia da marca no primeiro retoque. */}
                        <img src="/images/logo-gpe-cloud-full.png" alt="GPE Cloud — Gestão Pública Eficiente"
                            className="h-24 xl:h-28 w-auto mb-10 select-none" />

                        <h1 className="text-4xl font-extrabold leading-tight text-slate-800">Bem-vindo de volta!</h1>
                        <p className="mt-3 text-lg text-slate-500 max-w-md">
                            <strong className="text-slate-700">GPE Docs</strong> - Gestão Eletrônica de Documentos e Processos.
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
                        <div className="mb-8 lg:hidden">
                            <img src="/images/logo-gpe-cloud-full.png" alt="GPE Cloud" className="h-11 w-auto" />
                        </div>

                        <h2 className="text-2xl font-bold text-slate-800">Entrar</h2>
                        <p className="text-sm text-slate-400 mt-1 mb-6">Faça login para acessar sua conta</p>

                        {flash?.success && (
                            <div className="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl flex items-center gap-2">
                                <i className="fas fa-check-circle" /> {flash.success}
                            </div>
                        )}

                        {/* Erro vindo de redirecionamento (gov.br, Google, verificação em duas etapas, suspensão):
                            chega pelas props da página, não pelo formulário */}
                        {erroEntrada && !errors.login && (
                            <div className="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded-xl flex items-start gap-2">
                                <i className="fas fa-exclamation-circle mt-0.5" /> {erroEntrada}
                            </div>
                        )}

                        {comCertificado ? (
                            <EntrarComCertificado lembrar={data.remember} onVoltar={() => setComCertificado(false)} />
                        ) : (
                        <>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* E-mail ou CPF */}
                            <div>
                                <label className="block text-sm font-medium text-slate-600 mb-1.5">CPF ou E-mail</label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={data.login}
                                        onChange={(e) => setData('login', e.target.value)}
                                        placeholder="Digite seu CPF ou e-mail"
                                        autoFocus
                                        autoComplete="username"
                                        className={`w-full pl-4 pr-11 py-3 rounded-xl text-sm text-slate-800 placeholder-slate-400 border bg-slate-50/60 outline-none transition-all
                                            ${errors.login ? 'border-red-400 focus:ring-2 focus:ring-red-200' : 'border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100'}`}
                                    />
                                    <i className="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
                                </div>
                                {errors.login && <p className="mt-1.5 text-sm text-red-600 flex items-center gap-1.5"><i className="fas fa-exclamation-circle text-xs" /> {errors.login}</p>}
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

                            {/* Depois de algumas senhas erradas: código da imagem */}
                            {exige_captcha && (
                                <Captcha contexto="login" value={data.captcha}
                                    onChange={(v) => setData('captcha', v)} error={errors.captcha} />
                            )}

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

                        <div className="flex items-center gap-3 my-5">
                            <span className="flex-1 h-px bg-slate-200" />
                            <span className="text-xs text-slate-400">ou</span>
                            <span className="flex-1 h-px bg-slate-200" />
                        </div>
                        <button type="button" onClick={() => setComCertificado(true)}
                            className="w-full py-3 rounded-xl text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50 flex items-center justify-center gap-2">
                            <i className="fas fa-id-card text-cyan-600" /> Entrar com certificado digital
                        </button>
                        {provedores.includes('govbr') && (
                            <a href={`/login/oidc/govbr${data.remember ? '?lembrar=1' : ''}`}
                                className="mt-2 w-full py-3 rounded-xl text-sm font-semibold text-white bg-[#1351B4] hover:bg-[#0c326f] flex items-center justify-center gap-2">
                                Entrar com <span className="font-extrabold">gov.br</span>
                            </a>
                        )}
                        {provedores.includes('google') && (
                            <a href={`/login/oidc/google${data.remember ? '?lembrar=1' : ''}`}
                                className="mt-2 w-full py-3 rounded-xl text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50 flex items-center justify-center gap-2">
                                <i className="fab fa-google text-[#4285F4]" /> Entrar com Google
                            </a>
                        )}
                        </>
                        )}

                        <p className="lg:hidden text-center text-[11px] text-slate-400 mt-8">
                            © {new Date().getFullYear()} Conceito Gestão Pública — Assessoria &amp; Tecnologia
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}