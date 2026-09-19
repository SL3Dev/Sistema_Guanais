// service-worker.js - Espaço Guanais
// Cacheia o "app shell" (HTML/CSS/JS/ícones) para carregamento rápido e uso
// parcialmente offline. Chamadas de API (api/*.php) NUNCA são cacheadas -
// sempre vão direto pra rede, para nunca servir dados de paciente/financeiro
// desatualizados ou de outra sessão.

const CACHE_NAME = 'espaco-guanais-v1';
const APP_SHELL = [
    './',
    'index.html',
    'css/style.css',
    'js/script.js',
    'icons/icon-192.png',
    'icons/icon-512.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(APP_SHELL))
            .catch(() => {}) // não falha a instalação se algum asset opcional não carregar
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Nunca interceptar chamadas de API - sempre buscar da rede
    if (url.pathname.includes('/api/')) {
        return;
    }

    // Só cachear GET; outros métodos passam direto
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            const buscaRede = fetch(event.request)
                .then((response) => {
                    if (response && response.status === 200 && response.type === 'basic') {
                        const copia = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copia));
                    }
                    return response;
                })
                .catch(() => cached);
            return cached || buscaRede;
        })
    );
});
