/**
 * Convite público do participante (TR 4.48.31): confirma nome, CPF/CNPJ, registro profissional
 * e contato, sem login; depois acompanha o processo pelo portal com o CPF/CNPJ.
 */
import { Head, useForm, usePage } from '@inertiajs/react';

export default function ConviteParticipante({ token, confirmado, participante: pt, processo }) {
    const flash = usePage().props.flash || {};
    const form = useForm({ nome: pt.nome || '', cpf_cnpj: pt.cpf_cnpj || '', registro: pt.registro || '', email: pt.email || '', telefone: pt.telefone || '' });
    const campo = (k, rotulo, extra = {}) => (
        <label className="block text-sm text-gray-700">{rotulo}
            <input value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" {...extra} />
            {form.errors[k] && <span className="text-xs text-red-600">{form.errors[k]}</span>}
        </label>
    );

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Confirmar participação" />
            <div className="w-full max-w-lg rounded-2xl bg-white shadow p-6">
                <p className="text-xs text-gray-500">{processo.orgao}</p>
                <h1 className="text-lg font-semibold text-gray-800 mt-1">Confirmar participação</h1>
                <p className="text-sm text-gray-600 mt-1">Protocolo <b>{processo.numero}</b> — {processo.assunto}<br />Papel: <b>{pt.papel}</b></p>
                {(confirmado || flash.success) ? (
                    <p className="mt-4 rounded-lg bg-emerald-50 text-emerald-800 p-3 text-sm">{flash.success || 'Participação já confirmada.'} Acompanhe o processo pelo portal com o seu CPF/CNPJ.</p>
                ) : (
                    <form onSubmit={(e) => { e.preventDefault(); form.post(`/participantes/convite/${token}`); }} className="mt-4 space-y-3">
                        {campo('nome', 'Nome completo')}
                        <div className="grid grid-cols-2 gap-3">{campo('cpf_cnpj', 'CPF ou CNPJ', { inputMode: 'numeric' })}{campo('registro', 'Registro (CREA/CAU, ART/RRT)')}</div>
                        <div className="grid grid-cols-2 gap-3">{campo('email', 'E-mail', { type: 'email' })}{campo('telefone', 'Telefone')}</div>
                        <button type="submit" disabled={form.processing} className="w-full rounded-lg bg-cyan-700 text-white py-2.5 font-semibold disabled:opacity-60">Confirmar</button>
                    </form>
                )}
            </div>
        </div>
    );
}