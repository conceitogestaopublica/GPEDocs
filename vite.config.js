import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// Porta do Vite vista pelo navegador (VITE_PORT do .env, repassada pelo docker-compose).
const portaPublica = Number(process.env.VITE_PORT || 5174);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx', 'resources/js/fiscal/main.jsx'],
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
        origin: `http://localhost:${portaPublica}`,
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
            // clientPort (e não port): o websocket segue no servidor da 5174 do container; só o
            // endereço que o navegador usa muda para a porta publicada.
            clientPort: portaPublica,
        },
    },
});
