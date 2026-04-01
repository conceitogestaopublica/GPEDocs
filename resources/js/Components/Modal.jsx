import { useEffect } from 'react';

export default function Modal({ show, onClose, title, maxWidth = 'lg', children }) {
    useEffect(() => {
        if (show) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [show]);

    if (!show) return null;

    const widthClass = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl', '4xl': 'max-w-4xl' }[maxWidth] || 'max-w-lg';

    return (
        <div className="fixed inset-0 z-[70] flex items-center justify-center">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative bg-white rounded-2xl shadow-2xl w-full ${widthClass} mx-4 animate-fadeIn`}>
                {title && (
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
                        <button onClick={onClose} className="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600">
                            <i className="fas fa-times" />
                        </button>
                    </div>
                )}
                <div className="px-6 py-4">{children}</div>
            </div>
        </div>
    );
}
