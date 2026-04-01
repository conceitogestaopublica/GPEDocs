/**
 * StatusPill — Design System ERP Premium
 *
 * Pills exclusivamente para status. Cores semânticas.
 * Variantes: success | warning | danger | info
 */
const styles = {
    success: 'ds-pill ds-pill-success',
    warning: 'ds-pill ds-pill-warning',
    danger:  'ds-pill ds-pill-danger',
    info:    'ds-pill ds-pill-info',
};

export default function StatusPill({ status = 'info', label, children, className = '' }) {
    return (
        <span className={`${styles[status] || styles.info} ${className}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${
                status === 'success' ? 'bg-green-500' :
                status === 'warning' ? 'bg-amber-500' :
                status === 'danger' ? 'bg-red-500' : 'bg-blue-500'
            }`} />
            {label || children}
        </span>
    );
}
