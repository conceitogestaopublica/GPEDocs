import { useEffect, useRef, useState } from 'react';
import Button from './Button';

/**
 * Janela de confirmação/aviso padrão do sistema — substitui `window.confirm` e
 * `window.alert` (padrão do gpe2, no visual do GPE Docs).
 *
 * O nativo não segue o design system, não formata nada e não distingue "confirme uma
 * rotina" de "confirme algo irreversível": os dois saem com o mesmo OK/Cancelar.
 *
 * Normalmente não é usado direto: `useConfirm()` / `useAvisar()` (ConfirmProvider)
 * abrem este mesmo componente, montado uma vez no app.
 *
 * Props:
 *   open, onClose, onConfirm
 *   titulo          — frase curta do que vai acontecer
 *   descricao       — texto ou JSX explicando a consequência
 *   detalhes        — array de strings: bullets do que muda (opcional)
 *   tom             — 'info' (rotina) | 'atencao' (perde dado / irreversível) | 'perigo' (destruição)
 *   rotuloConfirmar, rotuloCancelar
 *   somenteOk       — aviso: um botão só (o "alert")
 *   exigirTexto     — o botão só habilita se o usuário digitar essa palavra
 */
const TONS = {
    info: {
        icone: 'fas fa-circle-question',
        iconeFundo: 'bg-blue-100 text-blue-600',
        caixa: 'bg-blue-50 border-blue-200 text-blue-900',
        variante: 'primary',
        botao: '',
    },
    atencao: {
        icone: 'fas fa-triangle-exclamation',
        iconeFundo: 'bg-amber-100 text-amber-600',
        caixa: 'bg-amber-50 border-amber-200 text-amber-900',
        variante: 'primary',
        botao: '!bg-amber-500 hover:!bg-amber-600 !border-amber-500',
    },
    perigo: {
        icone: 'fas fa-circle-exclamation',
        iconeFundo: 'bg-red-100 text-red-600',
        caixa: 'bg-red-50 border-red-200 text-red-900',
        variante: 'danger',
        botao: '',
    },
    sucesso: {
        icone: 'fas fa-circle-check',
        iconeFundo: 'bg-emerald-100 text-emerald-600',
        caixa: 'bg-emerald-50 border-emerald-200 text-emerald-900',
        variante: 'success',
        botao: '',
    },
};

export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    titulo = 'Confirmar',
    descricao = null,
    detalhes: detalhesProp = [],
    tom = 'info',
    rotuloConfirmar = 'Confirmar',
    rotuloCancelar = 'Cancelar',
    somenteOk = false,
    exigirTexto = null,
}) {
    // Aceita string por tolerância: `'texto'.map` derrubaria a tela.
    const detalhes = Array.isArray(detalhesProp) ? detalhesProp : (detalhesProp ? [String(detalhesProp)] : []);

    const [digitado, setDigitado] = useState('');
    const inputRef = useRef(null);
    const botaoRef = useRef(null);
    const cls = TONS[tom] || TONS.info;

    useEffect(() => {
        if (open) setDigitado('');
    }, [open]);

    // Esc fecha (como no nativo); Enter confirma quando não há texto a digitar.
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => { if (e.key === 'Escape') onClose?.(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    useEffect(() => {
        if (!open) return;
        if (exigirTexto) inputRef.current?.focus();
        else botaoRef.current?.querySelector('button')?.focus();
    }, [open, exigirTexto]);

    if (!open) return null;

    const liberado = !exigirTexto || digitado.trim().toUpperCase() === exigirTexto.toUpperCase();

    const confirmar = () => {
        if (!liberado) return;
        onConfirm?.();
        onClose?.();
    };

    // z-[110]: pode ser pedida de dentro de um Modal (z-[70]) — fica acima de tudo.
    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center p-4"
            role="alertdialog" aria-modal="true" aria-label={titulo}>
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl animate-fadeIn">

                <div className="flex items-start gap-4 px-6 pt-6 pb-2">
                    <div className={`w-11 h-11 shrink-0 rounded-full flex items-center justify-center ${cls.iconeFundo}`}>
                        <i className={`${cls.icone} text-lg`} />
                    </div>
                    <div className="min-w-0 flex-1 pt-1.5 space-y-3">
                        <h3 className="text-base font-semibold text-gray-800">{titulo}</h3>

                        {descricao && <div className="text-sm leading-relaxed text-gray-600">{descricao}</div>}

                        {detalhes.length > 0 && (
                            <ul className={`space-y-1.5 rounded-xl border px-3 py-2.5 text-sm ${cls.caixa}`}>
                                {detalhes.map((d, i) => (
                                    <li key={i} className="flex gap-2">
                                        <i className="fas fa-angle-right mt-1 shrink-0 text-xs opacity-60" />
                                        <span>{d}</span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {exigirTexto && (
                            <label className="block">
                                <span className="text-xs text-gray-500">
                                    Para confirmar, digite <strong className="font-semibold text-gray-700">{exigirTexto}</strong>:
                                </span>
                                <input
                                    ref={inputRef}
                                    value={digitado}
                                    onChange={(e) => setDigitado(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') confirmar(); }}
                                    className="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm outline-none focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    placeholder={exigirTexto}
                                    autoComplete="off"
                                />
                            </label>
                        )}
                    </div>
                </div>

                <div ref={botaoRef} className="flex flex-row-reverse gap-2 px-6 py-4 mt-2 bg-gray-50 border-t border-gray-100">
                    <Button onClick={confirmar} variant={cls.variante} className={cls.botao} disabled={!liberado}>
                        {somenteOk ? (rotuloConfirmar === 'Confirmar' ? 'Entendi' : rotuloConfirmar) : rotuloConfirmar}
                    </Button>
                    {!somenteOk && <Button onClick={onClose} variant="secondary">{rotuloCancelar}</Button>}
                </div>
            </div>
        </div>
    );
}
