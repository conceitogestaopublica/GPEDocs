/**
 * Tela de Login — GPE Docs
 * Layout estilo GPE Cloud: form a esquerda, imagem a direita
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
            <div className="min-h-screen flex flex-col lg:flex-row">
                {/* COLUNA ESQUERDA - Login */}
                <div className="w-full lg:w-5/12 flex flex-col items-center justify-center p-8 lg:p-12 relative"
                    style={{
                        background: 'linear-gradient(90deg, rgba(90,176,197,1) 0%, rgba(231,219,206,1) 100%)',
                        minHeight: '100vh',
                    }}>

                    {/* Logo */}
                    <div className="mb-8 text-center flex flex-col items-center">
                        <div className="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg mb-3 border-2 border-white/40">
                            <i className="fas fa-archive text-white text-3xl drop-shadow-lg" />
                        </div>
                        <h1 className="text-4xl font-bold text-white drop-shadow-lg tracking-wide">
                            GPE <span className="text-orange-400">Docs</span>
                        </h1>
                        <p className="text-sm text-white/90 font-medium mt-1">Plataforma Digital Integrada</p>
                    </div>

                    {/* Form */}
                    <form onSubmit={submit} className="w-full max-w-sm space-y-4">
                        <div className="relative">
                            <i className="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="E-mail"
                                autoFocus
                                className="w-full pl-12 pr-4 py-3 rounded-xl border-0 bg-white/95 text-gray-800 placeholder-gray-500 font-semibold shadow-md focus:outline-none focus:ring-2 focus:ring-white/50"
                            />
                        </div>

                        <div className="relative">
                            <i className="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Senha"
                                className="w-full pl-12 pr-4 py-3 rounded-xl border-0 bg-white/95 text-gray-800 placeholder-gray-500 font-semibold shadow-md focus:outline-none focus:ring-2 focus:ring-white/50"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-3 rounded-xl bg-white/20 backdrop-blur-sm text-white font-bold border-2 border-white shadow-lg hover:bg-white/30 transition-all disabled:opacity-50"
                        >
                            {processing ? (
                                <><i className="fas fa-spinner fa-spin mr-2" />Entrando...</>
                            ) : 'Acessar'}
                        </button>

                        {(errors.email || errors.password) && (
                            <div className="bg-red-500/90 text-white text-sm px-4 py-2 rounded-xl shadow-md">
                                <i className="fas fa-exclamation-circle mr-2" />
                                {errors.email || errors.password}
                            </div>
                        )}
                    </form>

                    {/* Link recuperar senha */}
                    <div className="mt-6 text-center">
                        <small className="text-white/90">
                            Esqueceu sua senha?{' '}
                            <a href="#" className="text-orange-500 font-bold uppercase underline hover:text-orange-400">
                                Clique aqui
                            </a>{' '}
                            para recuperar!
                        </small>
                    </div>

                    {/* Icones sociais */}
                    <div className="flex gap-4 mt-8">
                        <a href="https://www.instagram.com/conceito.tecnologia" target="_blank" rel="noreferrer"
                            className="text-white text-2xl hover:scale-125 transition-transform" title="Instagram">
                            <i className="fa-brands fa-instagram" />
                        </a>
                        <a href="https://wa.me/553399475775" target="_blank" rel="noreferrer"
                            className="text-white text-2xl hover:scale-125 transition-transform" title="WhatsApp">
                            <i className="fa-brands fa-whatsapp" />
                        </a>
                        <a href="https://conceitogestaopublica.com.br/" target="_blank" rel="noreferrer"
                            className="text-white text-2xl hover:scale-125 transition-transform" title="Site">
                            <i className="fas fa-globe" />
                        </a>
                    </div>

                    {/* Copyright */}
                    <small className="absolute bottom-4 text-white/80 font-medium">
                        © Conceito - Assessoria & Tecnologia
                    </small>
                </div>

                {/* COLUNA DIREITA - Imagem/Conteudo */}
                <div className="hidden lg:flex w-full lg:w-7/12 bg-gray-50 items-center justify-center p-8">
                    <div className="text-center max-w-2xl">
                        {/* Placeholder para imagem futura */}
                        <div className="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-3xl p-12 shadow-xl">
                            <i className="fas fa-image text-6xl text-blue-300 mb-4 block" />
                            <h2 className="text-2xl font-bold text-gray-700 mb-2">Plataforma Digital Integrada</h2>
                            <p className="text-gray-500">Espaço reservado para imagem promocional</p>
                            <p className="text-xs text-gray-400 mt-4">A definir...</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
