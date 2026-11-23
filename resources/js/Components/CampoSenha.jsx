/**
 * Campo de senha com botão de olho para mostrar/ocultar o que foi digitado.
 *
 * Repassa todas as props ao <input> (value, onChange, name, id, autoComplete,
 * required, placeholder, disabled, className, ref...), então cada tela mantém
 * o próprio estilo. O texto não passa por baixo do olho: se o className não
 * define um padding à direita (pr-N), entra pr-10 (a largura do botão).
 * Margens/largura do bloco vão em `wrapperClassName` (ex.: mt-1, w-56), para o
 * olho ficar centralizado na altura do input.
 * O invólucro é um <span> para poder ficar dentro de <label>.
 */
import { useState } from 'react';

export default function CampoSenha({ className = '', wrapperClassName = '', disabled, ref, ...props }) {
    const [visivel, setVisivel] = useState(false);
    const rotulo = visivel ? 'Ocultar senha' : 'Mostrar senha';
    // pr-N menor que pr-10 deixaria o texto correr por baixo do olho: troca por pr-10.
    const prInformado = className.match(/(?:^|\s)pr-(\d+)(?=\s|$)/)?.[1];
    const pr = Number(prInformado) >= 10 ? prInformado : null;
    const base = prInformado && !pr ? className.replace(/(^|\s)pr-\d+(?=\s|$)/, '$1') : className;
    const classeInput = `${base} ${pr ? '' : 'pr-10'}`.trim();
    // Botão com a mesma largura do padding direito do input (o olho fica centrado no espaço reservado).
    const larguraBotao = { 11: 'w-11', 12: 'w-12' }[pr] ?? 'w-10';

    return (
        <span className={`relative block ${wrapperClassName}`.trim()}>
            <input {...props} ref={ref} disabled={disabled} type={visivel ? 'text' : 'password'} className={classeInput} />
            <button
                type="button"
                onClick={() => setVisivel((v) => !v)}
                disabled={disabled}
                aria-label={rotulo}
                aria-pressed={visivel}
                title={rotulo}
                className={`absolute inset-y-0 right-0 flex ${larguraBotao} items-center justify-center rounded-md text-slate-400 transition-colors hover:text-slate-600 focus-visible:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:text-slate-400`}
            >
                <i className={`fas ${visivel ? 'fa-eye-slash' : 'fa-eye'} text-sm`} aria-hidden="true" />
            </button>
        </span>
    );
}
