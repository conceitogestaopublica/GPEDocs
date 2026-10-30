/**
 * Página do e-mail de resolução (TR 4.26.8), sem login: o usuário concorda e avalia o atendimento
 * (nota de 1 a 5) ou reabre explicando o que falta.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

export default function Avaliar({ atendimento: a, token, reabrir: modoReabrir, resumo }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [reabrir, setReabrir] = useState(modoReabrir);
    const [nota, setNota] = useState(0);
    const [texto, setTexto] = useState('');
    const enviar = (e) => {
        e.preventDefault();
        router.post(`/suporte/avaliar/${token}`, reabrir ? { reabrir: 1, motivo: texto } : { nota, comentario: texto }, { preserveScroll: true });
    };
    const pronto = flash.success;

    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <Head title={`Atendimento ${a.numero}`} />
            <div className="mx-auto max-w-lg rounded-2xl bg-white p-6 shadow">
                <p className="text-xs text-gray-500">Suporte — atendimento {a.numero}</p>
                <h1 className="mt-1 text-lg font-semibold text-gray-800">{a.titulo}</h1>
                <p className="mt-1 text-xs text-gray-500">Aberto em {resumo.aberto_em}{resumo.resolvido_em ? ` · resolvido em ${resumo.resolvido_em}` : ''}
                    {resumo.atendentes.length ? ` · atendido por ${resumo.atendentes.map((x) => x.nome).join(', ')}` : ''}</p>

                {pronto ? <p className="mt-5 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{pronto}</p>
                    : a.avaliado && ! reabrir ? <p className="mt-5 text-sm text-gray-600">Este atendimento já foi avaliado ({'★'.repeat(a.nota)}). Obrigado!</p>
                    : (
                        <form onSubmit={enviar} className="mt-5 space-y-3">
                            <div className="inline-flex overflow-hidden rounded-lg border border-gray-300 text-sm">
                                <button type="button" onClick={() => setReabrir(false)} className={`px-3 py-1.5 ${! reabrir ? 'bg-emerald-600 text-white' : 'bg-white'}`}>Resolveu: avaliar</button>
                                <button type="button" onClick={() => setReabrir(true)} className={`px-3 py-1.5 ${reabrir ? 'bg-red-600 text-white' : 'bg-white'}`}>Não resolveu: reabrir</button>
                            </div>
                            {! reabrir && (
                                <div className="flex gap-1 text-3xl" role="radiogroup" aria-label="Nota">
                                    {[1, 2, 3, 4, 5].map((n) => <button key={n} type="button" onClick={() => setNota(n)} aria-label={`Nota ${n}`} className={n <= nota ? 'text-amber-400' : 'text-gray-300'}>★</button>)}
                                </div>
                            )}
                            <textarea value={texto} onChange={(e) => setTexto(e.target.value)} rows={4} className="w-full rounded-lg border border-gray-300 p-2 text-sm"
                                placeholder={reabrir ? 'O que ainda não foi resolvido?' : 'Comentário (opcional)'} />
                            {Object.values(errors)[0] && <p className="text-xs text-red-600">{Object.values(errors)[0]}</p>}
                            <button type="submit" disabled={reabrir ? ! texto.trim() : ! nota}
                                className={`w-full rounded-lg py-2.5 text-sm font-semibold text-white disabled:opacity-40 ${reabrir ? 'bg-red-600' : 'bg-emerald-600'}`}>{reabrir ? 'Reabrir o atendimento' : 'Enviar avaliação'}</button>
                        </form>
                    )}
            </div>
        </div>
    );
}
