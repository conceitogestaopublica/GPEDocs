/**
 * GPE Docs - Plataforma Digital Integrada
 *
 * Ponto de entrada da aplicacao React + Inertia.js
 */
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ConfirmProvider } from './Components/ConfirmProvider';

createInertiaApp({
    title: (title) => title ? `${title} - GPE Docs` : 'GPE Docs - Plataforma Digital Integrada',

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Página não encontrada: ./Pages/${name}.jsx`);
        }
        return page;
    },

    setup({ el, App, props }) {
        // ConfirmProvider monta UMA janela de confirmação/aviso (+ notificações) para o
        // app inteiro; as telas usam useConfirm/useAvisar/useNotificar.
        createRoot(el).render(
            <ConfirmProvider>
                <App {...props} />
            </ConfirmProvider>
        );
    },
});
