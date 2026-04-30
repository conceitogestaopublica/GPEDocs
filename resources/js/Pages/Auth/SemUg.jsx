/**
 * Tela exibida quando o usuario logou mas nao tem nenhuma UG vinculada.
 */
import { Head, router } from '@inertiajs/react';

export default function SemUg({ user }) {
    return (
        <div className="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex items-center justify-center p-4">
            <Head title="Sem acesso a UG" />

            <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
                <div className="w-16 h-16 mx-auto mb-4 bg-amber-100 rounded-2xl flex items-center justify-center">
                    <i className="fas fa-exclamation-triangle text-amber-600 text-2xl" />
                </div>

                <h1 className="text-xl font-bold text-gray-800 mb-2">
                    Voce ainda nao tem acesso a nenhuma UG
                </h1>

                <p className="text-sm text-gray-600 mb-6 leading-relaxed">
                    Voce esta logado como <strong className="text-gray-800">{user.name}</strong>,
                    mas seu usuario nao foi vinculado a nenhuma Unidade Gestora.
                    Procure o administrador do sistema para liberar seu acesso.
                </p>

                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-left">
                    <p className="text-[11px] text-amber-800 leading-relaxed">
                        <i className="fas fa-info-circle mr-1" />
                        <strong>Informe ao administrador:</strong>
                    </p>
                    <p className="text-[11px] text-amber-700 mt-1 font-mono">
                        {user.email}
                    </p>
                </div>

                <button onClick={() => router.post('/logout')}
                    className="w-full px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-colors text-sm">
                    <i className="fas fa-sign-out-alt mr-1" />
                    Sair
                </button>
            </div>
        </div>
    );
}
