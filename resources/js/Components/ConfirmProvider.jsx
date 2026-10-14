import { createContext, useCallback, useContext, useRef, useState } from 'react';
import ConfirmDialog from './ConfirmDialog';

/**
 * Janelas do sistema — UM provider para o app inteiro (montado em `app.jsx`), padrão gpe2.
 *
 * Em vez de cada tela replicar `useState(aberto)` + JSX do dialog (ou cair no
 * `window.confirm`/`alert` nativo, fora do design system), o call site chama uma função:
 *
 *     const confirmar = useConfirm();
 *     // antes:  if (!confirm('Excluir?')) return;
 *     // depois: if (!await confirmar('Excluir?')) return;
 *
 *     await confirmar({ titulo: 'Excluir documento?', descricao: '...', tom: 'perigo',
 *                       rotuloConfirmar: 'Excluir', exigirTexto: 'EXCLUIR' });
 *
 *     const avisar = useAvisar();          // antes: alert('Informe o parecer.')
 *     await avisar({ titulo: 'Informe o parecer', tom: 'atencao' });
 *
 *     const notificar = useNotificar();    // aviso rápido no canto, some sozinho
 *     notificar('Token copiado!');                    // sucesso
 *     notificar({ mensagem: 'Falhou', tipo: 'error' });
 */
const DialogoContext = createContext(null);

const TOAST_ESTILO = {
    success: { caixa: 'border-emerald-200', icone: 'fas fa-circle-check text-emerald-500' },
    error:   { caixa: 'border-red-200',     icone: 'fas fa-circle-exclamation text-red-500' },
    warning: { caixa: 'border-amber-200',   icone: 'fas fa-triangle-exclamation text-amber-500' },
    info:    { caixa: 'border-blue-200',    icone: 'fas fa-circle-info text-blue-500' },
};

export function ConfirmProvider({ children }) {
    const [config, setConfig] = useState(null);
    const resolverRef = useRef(null);
    const [toasts, setToasts] = useState([]);
    const seq = useRef(0);

    // Resolve a promise pendente e fecha. Zera o resolver antes: confirmar dispara
    // onConfirm E onClose no ConfirmDialog, e só a primeira resposta vale.
    const decidir = useCallback((resposta) => {
        const resolve = resolverRef.current;
        resolverRef.current = null;
        setConfig(null);
        resolve?.(resposta);
    }, []);

    const abrir = useCallback((opcoes, extra = {}) => {
        // Chamada nova enquanto uma está aberta: a anterior é cancelada, não fica pendurada.
        resolverRef.current?.(false);
        const props = typeof opcoes === 'string' ? { titulo: opcoes } : (opcoes || {});

        return new Promise((resolve) => {
            resolverRef.current = resolve;
            setConfig({ ...props, ...extra });
        });
    }, []);

    const confirmar = useCallback((opcoes) => abrir(opcoes), [abrir]);
    const avisar = useCallback((opcoes) => abrir(opcoes, { somenteOk: true }).then(() => undefined), [abrir]);

    const fecharToast = useCallback((id) => setToasts((t) => t.filter((x) => x.id !== id)), []);
    const notificar = useCallback((opcoes) => {
        const o = typeof opcoes === 'string' ? { mensagem: opcoes } : (opcoes || {});
        const id = ++seq.current;
        setToasts((t) => [...t, { id, tipo: o.tipo || 'success', mensagem: o.mensagem }]);
        setTimeout(() => fecharToast(id), o.duracao ?? 4000);
    }, [fecharToast]);

    return (
        <DialogoContext.Provider value={{ confirmar, avisar, notificar }}>
            {children}
            <ConfirmDialog
                {...(config || {})}
                open={config !== null}
                onConfirm={() => decidir(true)}
                onClose={() => decidir(false)}
            />
            {toasts.length > 0 && (
                <div className="fixed top-4 right-4 z-[120] flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)]" aria-live="polite">
                    {toasts.map((t) => {
                        const e = TOAST_ESTILO[t.tipo] || TOAST_ESTILO.info;
                        return (
                            <div key={t.id} role="status"
                                className={`flex items-start gap-3 px-4 py-3 rounded-xl border bg-white shadow-lg animate-slideInRight ${e.caixa}`}>
                                <i className={`${e.icone} mt-0.5`} />
                                <span className="flex-1 text-sm text-gray-700">{t.mensagem}</span>
                                <button type="button" onClick={() => fecharToast(t.id)} title="Fechar" aria-label="Fechar"
                                    className="w-6 h-6 -mt-0.5 -mr-1 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                                    <i className="fas fa-times text-xs" />
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </DialogoContext.Provider>
    );
}

/**
 * Fora do provider (teste isolado) cai no nativo em vez de quebrar a tela — a
 * confirmação continua acontecendo, só sem o design system.
 */
const texto = (o, padrao) => (typeof o === 'string' ? o : (o?.titulo ?? o?.mensagem ?? padrao));

/** `confirmar(opcoes) => Promise<boolean>` */
export function useConfirm() {
    const ctx = useContext(DialogoContext);
    return ctx ? ctx.confirmar : (o) => Promise.resolve(window.confirm(texto(o, 'Confirmar')));
}

/** `avisar(opcoes) => Promise<void>` — janela com um botão só (o "alert"). */
export function useAvisar() {
    const ctx = useContext(DialogoContext);
    return ctx ? ctx.avisar : (o) => Promise.resolve(window.alert(texto(o, '')));
}

/** `notificar(mensagem | { mensagem, tipo, duracao })` — aviso rápido no canto. */
export function useNotificar() {
    const ctx = useContext(DialogoContext);
    return ctx ? ctx.notificar : (o) => window.alert(texto(o, ''));
}

export default ConfirmProvider;
