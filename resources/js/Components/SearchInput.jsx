/**
 * SearchInput — Design System ERP Premium
 *
 * Input de busca com ícone, debounce e botão limpar.
 * Altura: 44px | Radius: 10px
 */
import { useState, useCallback, useRef, useEffect } from 'react';

export default function SearchInput({ value = '', onChange, onSubmit, placeholder = 'Buscar...', debounce = 300, className = '' }) {
    const [local, setLocal] = useState(value);
    const timerRef = useRef(null);

    useEffect(() => { setLocal(value); }, [value]);

    const handleChange = useCallback((e) => {
        const val = e.target.value;
        setLocal(val);
        if (onChange && debounce > 0) {
            clearTimeout(timerRef.current);
            timerRef.current = setTimeout(() => onChange(val), debounce);
        }
    }, [onChange, debounce]);

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && onSubmit) {
            e.preventDefault();
            onSubmit(local);
        }
    };

    const clear = () => {
        setLocal('');
        onChange?.('');
        onSubmit?.('');
    };

    return (
        <div className={`ds-search ${className}`}>
            <i className="fas fa-search ds-search-icon text-sm" />
            <input
                type="text"
                value={local}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                className="ds-input !pl-9 !pr-9"
            />
            {local && (
                <button type="button" onClick={clear}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--ds-text-muted)] hover:text-[var(--ds-text)]">
                    <i className="fas fa-times text-xs" />
                </button>
            )}
        </div>
    );
}
