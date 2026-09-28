import Alpine from 'alpinejs';
import dayBoard from './day-board';

Alpine.data('dayBoard', dayBoard);

window.Alpine = Alpine;
Alpine.start();

// PWA: el service worker vive en la raíz pública (su scope es la app completa).
const swUrl = document.querySelector('meta[name="sw-url"]')?.content;
if (swUrl && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(swUrl).catch((e) => console.warn('SW no registrado:', e.message));
    });
}
