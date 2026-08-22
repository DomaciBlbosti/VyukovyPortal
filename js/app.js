// js/app.js — obecné UI pomocné funkce
document.addEventListener('DOMContentLoaded', function () {
    // Animace vstupu karet
    document.querySelectorAll('.stat-card, .game-card').forEach((el, i) => {
        el.style.animationDelay = (i * 0.07) + 's';
        el.classList.add('fade-in-up');
    });

    // Mobilní menu (hamburger)
    const navToggle = document.getElementById('navToggle');
    const navMenu   = document.getElementById('navMenu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const open = navMenu.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        // Zavři menu po kliknutí na odkaz
        navMenu.querySelectorAll('a').forEach(a =>
            a.addEventListener('click', () => navMenu.classList.remove('open')));
    }
});

// ─── PWA ────────────────────────────────────────────────
const BASE = window.BASE_URL || '';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(BASE + '/sw.js').catch(() => {});
    });
}

// Tlačítko "Instalovat" v menu — objeví se, když prohlížeč instalaci nabídne
let deferredInstall = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstall = e;
    const btn = document.getElementById('installBtn');
    if (btn) {
        btn.style.display = '';
        btn.addEventListener('click', async () => {
            btn.style.display = 'none';
            deferredInstall?.prompt();
            deferredInstall = null;
        }, { once: true });
    }
});
window.addEventListener('appinstalled', () => {
    const btn = document.getElementById('installBtn');
    if (btn) btn.style.display = 'none';
});
