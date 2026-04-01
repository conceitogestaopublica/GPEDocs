/**
 * Field — Design System ERP Premium
 *
 * Wrapper de campo de formulário: label + input + erro
 */
export default function Field({ label, error, required, className = '', children }) {
    return (
        <div className={className}>
            {label && (
                <label className="block text-[var(--ds-text-sm)] font-medium text-[var(--ds-text)] mb-1">
                    {label}{required && <span className="text-[var(--ds-danger)] ml-1">*</span>}
                </label>
            )}
            {children}
            {error && (
                <p className="mt-1 text-[var(--ds-text-xs)] text-[var(--ds-danger-text)]">
                    <i className="fas fa-exclamation-circle mr-1" />{error}
                </p>
            )}
        </div>
    );
}

/**
 * Classe utilitária para inputs padrão
 */
export function inputClass(error, disabled) {
    return `ds-input ${error ? '!border-[var(--ds-danger)]' : ''} ${disabled ? '!bg-[var(--ds-surface-muted)] !cursor-not-allowed' : ''}`;
}
