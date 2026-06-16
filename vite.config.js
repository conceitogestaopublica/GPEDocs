import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        sourcemap: true,
    },
    server: {
        // Escuta em todas interfaces — o container precisa expor pro host.
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        // URL publica que o laravel-vite-plugin grava em public/hot e que o
        // browser usa pra carregar os assets / conectar no HMR.
        origin: 'http://localhost:5174',
        cors: true,
        watch: {
            // inotify nao detecta mudancas em bind mount Windows -> Linux.
            // Polling resolve. Custo: leve uso de CPU constante.
            usePolling: true,
            interval: 300,
            ignored: ['**/storage/framework/views/**'],
        },
        hmr: {
            // Host que o browser usa pra abrir o websocket de HMR.
            host: 'localhost',
            port: 5174,
        },
    },
});
