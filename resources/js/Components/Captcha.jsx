/**
 * Captcha de imagem (App\Support\Captcha): mostra o código, deixa trocar a imagem e recebe o que foi digitado.
 * A imagem é pedida de novo a cada montagem e quando o servidor recusa o código (cada código vale uma vez).
 */
import { useEffect, useState } from 'react';

export default function Captcha({ contexto, value, onChange, error }) {
    const [nonce, setNonce] = useState(() => Date.now());

    // Código recusado: o servidor já descartou o anterior, então busca outro.
    useEffect(() => {
        if (error) {
            setNonce(Date.now());
            onChange('');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [error]);

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">Código de verificação</label>
            <div className="flex items-center gap-2">
                <img src={`/captcha/${contexto}?r=${nonce}`} alt="Código de verificação: digite os caracteres da imagem"
                    width={180} height={56} className="rounded-lg border border-gray-200 bg-white select-none" draggable={false} />
                <button type="button" onClick={() => { setNonce(Date.now()); onChange(''); }}
                    className="w-9 h-9 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Trocar a imagem"
                    aria-label="Trocar a imagem do código">
                    <i className="fas fa-sync-alt text-sm" />
                </button>
            </div>
            <input type="text" value={value} onChange={(e) => onChange(e.target.value.toUpperCase())}
                autoComplete="off" spellCheck={false} maxLength={8}
                placeholder="Digite o código da imagem"
                className="ds-input mt-2 uppercase tracking-widest" aria-label="Código de verificação" />
            {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
        </div>
    );
}
