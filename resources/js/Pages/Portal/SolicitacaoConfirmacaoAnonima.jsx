/**
 * Portal — Confirmacao de solicitacao ANONIMA (sem login).
 * Como nao podemos vincular ao cidadao, mostramos so o codigo de protocolo.
 */
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

export default function ConfirmacaoAnonima({ ug, codigo, servico }) {
    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`Solicitacao ${codigo} registrada`} />

            <div className="max-w-xl mx-auto bg-white rounded-2xl border border-gray-200 p-8 shadow-sm text-center">
                <div className="inline-flex w-20 h-20 bg-green-100 rounded-2xl items-center justify-center mb-4">
                    <i className="fas fa-check text-green-600 text-3xl" />
                </div>
                <h1 className="text-2xl font-bold text-gray-800 mb-2">Solicitacao registrada</h1>
                <p className="text-sm text-gray-600 mb-6">
                    Sua solicitacao para <strong>{servico.titulo}</strong> foi recebida pelo orgao responsavel
                    e sera analisada.
                </p>

                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-6 mb-6">
                    <p className="text-[10px] uppercase tracking-wider text-blue-600 font-bold">Codigo de protocolo</p>
                    <p className="text-3xl font-bold text-blue-800 font-mono mt-1">{codigo}</p>
                    <p className="text-xs text-gray-500 mt-3">
                        <i className="fas fa-info-circle mr-1" />
                        Anote este codigo. Como sua solicitacao e anonima, ele e a unica forma de referencia-la.
                    </p>
                </div>

                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-6">
                    <p className="text-sm font-bold text-amber-900 mb-1">
                        <i className="fas fa-shield-alt mr-1" /> Sua identidade nao foi registrada
                    </p>
                    <p className="text-xs text-amber-800">
                        Por ser uma denuncia/solicitacao anonima, nao temos como enviar atualizacoes por email.
                        Sua descricao e o codigo de protocolo foram encaminhados para o setor responsavel.
                    </p>
                </div>

                <Link href="/" className="inline-block px-6 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Voltar ao inicio
                </Link>
            </div>
        </PortalLayout>
    );
}
