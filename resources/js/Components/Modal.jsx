import { useEffect } from 'react';

export default function Modal({ show, onClose, title, maxWidth = 'lg', children }) {
    // Esc fecha — mesmo comportamento da janela de confirmação (ConfirmDialog).
    useEffect(() => {
        if (!show || !onClose) return;
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [show, onClose]);

    useEffect(() => {
        if (show) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [show]);

    if (!show) return null;

    const widthClass = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl', '4xl': 'max-w-4xl' }[maxWidth] || 'max-w-lg';

    return (
        <div className="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto py-4" role="dialog" aria-modal="true" aria-label={title}>
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative bg-white rounded-2xl shadow-2xl w-full ${widthClass} mx-4 my-auto animate-fadeIn max-h-[90vh] flex flex-col`}>
                {title && (
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/60 rounded-t-2xl shrink-0">
                        <h3 className="text-base font-semibold text-gray-800">{title}</h3>
                        <button type="button" onClick={onClose} title="Fechar" aria-label="Fechar" className="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600">
                            <i className="fas fa-times" />
                        </button>
                    </div>
                )}
                <div className="px-6 py-4 overflow-y-auto">{children}</div>
            </div>
        </div>
    );
}
