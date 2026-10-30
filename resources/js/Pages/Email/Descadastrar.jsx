/**
 * Página pública do link "deixar de receber" dos e-mails (DescadastroEmailController).
 */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Descadastrar({ email, descadastrado, acao }) {
    const [enviando, setEnviando] = useState(false);

    const enviar = (voltar) => router.post(acao, voltar ? { voltar: 1 } : {}, {
        preserveScroll: true,
        onStart: () => setEnviando(true),
        onFinish: () => setEnviando(false),
    });

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Avisos por e-mail" />
            <div className="w-full max-w-md bg-white rounded-2xl shadow-xl border border-gray-100 p-6 text-center">
                <i className={`fas ${descadastrado ? 'fa-bell-slash text-gray-400' : 'fa-bell text-primary-500'} text-3xl`} />
                <h1 className="text-lg font-semibold text-gray-800 mt-3">
                    {descadastrado ? 'Você não recebe mais os avisos automáticos' : 'Deixar de receber os avisos automáticos?'}
                </h1>
                <p className="text-sm text-gray-600 mt-2">
                    Endereço: <strong>{email}</strong>
                </p>
                <p className="text-xs text-gray-500 mt-3 leading-relaxed">
                    São os avisos de andamento das suas solicitações. Comunicações oficiais dirigidas a você
                    (ofícios, pedidos de assinatura) continuam chegando, e o andamento pode ser consultado no portal.
                </p>
                <button type="button" disabled={enviando} onClick={() => enviar(descadastrado)}
                    className={`ds-btn ${descadastrado ? 'ds-btn-outline' : 'ds-btn-primary'} w-full justify-center mt-5`}>
                    {enviando ? <i className="fas fa-spinner fa-spin" /> : (descadastrado ? 'Voltar a receber os avisos' : 'Deixar de receber')}
                </button>
            </div>
        </div>
    );
}
