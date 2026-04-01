/**
 * GED - Gestao Eletronica de Documentos
 *
 * Ponto de entrada da aplicacao React + Inertia.js
 */
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => title ? `${title} - GED` : 'GED - Gestao Eletronica de Documentos',

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Pagina nao encontrada: ./Pages/${name}.jsx`);
        }
        return page;
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
