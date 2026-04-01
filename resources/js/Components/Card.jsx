export default function Card({ title, subtitle, actions, padding = true, className = '', children }) {
    return (
        <div className={`ds-card ${!padding ? '!p-0' : ''} ${className.includes('overflow') ? '' : 'overflow-hidden'} ${className}`}>
            {title && (
                <div className="ds-card-title flex items-center justify-between">
                    <div>
                        <span>{title}</span>
                        {subtitle && <p className="text-[var(--ds-text-sm)] text-[var(--ds-text-muted)] mt-0.5 font-normal">{subtitle}</p>}
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </div>
            )}
            {children}
        </div>
    );
}
