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

// Instalace: Chrome na Androidu už sám žádnou výzvu neukazuje (nabídku má
// jen v menu ⋮), takže ji zobrazíme sami — banner dole + tlačítko v menu.
let deferredInstall = null;

function promptInstall() {
    deferredInstall?.prompt();
    deferredInstall = null;
    hideInstallUI();
}

function hideInstallUI() {
    document.getElementById('installBanner')?.remove();
    const btn = document.getElementById('installBtn');
    if (btn) btn.style.display = 'none';
}

function showInstallBanner() {
    if (document.getElementById('installBanner')) return;
    try { if (localStorage.getItem('installBannerDismissed')) return; } catch {}
    const b = document.createElement('div');
    b.id = 'installBanner';
    b.className = 'install-banner';
    b.innerHTML =
        '<span class="install-banner-text">📲 Přidej si TypeMaster na plochu jako aplikaci</span>' +
        '<button class="btn-primary install-banner-btn" type="button">Instalovat</button>' +
        '<button class="install-banner-close" type="button" aria-label="Zavřít">✕</button>';
    b.querySelector('.install-banner-btn').addEventListener('click', promptInstall);
    b.querySelector('.install-banner-close').addEventListener('click', () => {
        try { localStorage.setItem('installBannerDismissed', '1'); } catch {}
        b.remove();
    });
    document.body.appendChild(b);
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstall = e;
    const btn = document.getElementById('installBtn');
    if (btn) {
        btn.style.display = '';
        btn.addEventListener('click', promptInstall, { once: true });
    }
    showInstallBanner();
});
window.addEventListener('appinstalled', hideInstallUI);
