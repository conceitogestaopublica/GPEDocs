{{-- Service worker do app da fiscalização (TR 4.52.7). A tela abre da rede quando há internet e do
cache quando não há; os arquivos do build e as fontes vêm do cache. Os dados ficam no IndexedDB. --}}
const CACHE = 'gpe-fiscal-{{ substr(md5((string) @filemtime(public_path('build/manifest.json'))), 0, 8) }}';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

// Versão nova: só apaga os caches antigos depois de guardar a tela nova (a antiga continua abrindo offline até lá)
const limparAntigos = () => caches.keys().then((nomes) => Promise.all(nomes.filter((n) => n.startsWith('gpe-fiscal-') && n !== CACHE).map((n) => caches.delete(n))));

self.addEventListener('message', (e) => {
    if (e.data?.tipo !== 'guardar') return;
    e.waitUntil(caches.open(CACHE).then((c) => Promise.all((e.data.urls || []).map((u) =>
        fetch(u, new URL(u, self.location).origin === self.location.origin ? { credentials: 'same-origin' } : { mode: 'no-cors' })
            .then((r) => (r.ok || r.type === 'opaque') && ! r.redirected ? c.put(u, r) : null).catch(() => null)))));
});

const estatico = (url) => url.pathname.startsWith('/build/') || /cdnjs\.cloudflare\.com|fonts\.bunny\.net/.test(url.host);

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    if (req.mode === 'navigate' && url.pathname.replace(/\/$/, '') === '/fiscal') {
        e.respondWith(fetch(req).then((r) => {
            // sem login o servidor redireciona: não guarda a tela de login no lugar do app
            if (r.ok && ! r.redirected) { const copia = r.clone(); caches.open(CACHE).then((c) => c.put('/fiscal', copia)).then(limparAntigos); }
            return r;
        }).catch(() => caches.match('/fiscal')));
        return;
    }
    if (estatico(url)) {
        e.respondWith(caches.match(req).then((c) => c || fetch(req).then((r) => {
            if (r.ok || r.type === 'opaque') { const copia = r.clone(); caches.open(CACHE).then((x) => x.put(req, copia)); }
            return r;
        })));
    }
});
