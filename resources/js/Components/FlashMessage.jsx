/**
 * Componente de mensagem flash (alerta temporário).
 *
 * Exibe uma notificação que desaparece automaticamente após 8 segundos.
 *
 * Props:
 *   - type: 'success' | 'error' | 'warning' | 'info'
 *   - message: string — texto da mensagem
 */
import { useState, useEffect } from 'react';

const styles = {
    success: { bg: 'bg-green-50 border-green-400 text-green-800', icon: 'fas fa-check-circle text-green-500' },
    error:   { bg: 'bg-red-50 border-red-400 text-red-800',       icon: 'fas fa-exclamation-circle text-red-500' },
    warning: { bg: 'bg-yellow-50 border-yellow-400 text-yellow-800', icon: 'fas fa-exclamation-triangle text-yellow-500' },
    info:    { bg: 'bg-blue-50 border-blue-400 text-blue-800',     icon: 'fas fa-info-circle text-blue-500' },
};

export default function FlashMessage({ type = 'info', message }) {
    const [visible, setVisible] = useState(true);
    const style = styles[type] || styles.info;

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), 8000);
        return () => clearTimeout(timer);
    }, []);

    if (!visible) return null;

    return (
        <div className={`flex items-start gap-3 p-4 rounded-lg border ${style.bg} mb-4 animate-fade-in`}>
            <i className={`${style.icon} mt-0.5`} />
            <span className="flex-1 text-sm">{message}</span>
            <button onClick={() => setVisible(false)} className="opacity-50 hover:opacity-100">
                <i className="fas fa-times text-xs" />
            </button>
        </div>
    );
}
