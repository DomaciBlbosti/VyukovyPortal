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

// ─── Odměna po dohrané hře (body + postup na level) ─────
// Volají všechny hry po uložení výsledku; data = odpověď saveGameResult().
function renderReward(data, statusEl) {
    if (statusEl) statusEl.textContent = data && data.ok ? '✔ Výsledek uložen!' : '⚠ Nepodařilo se uložit.';
    if (!data || !data.ok || !data.level) return;

    const panel = document.querySelector('.results-panel');
    if (!panel) return;
    const anchor = panel.querySelector('h2');

    if (data.points > 0) {
        const badge = document.createElement('div');
        badge.className = 'points-earned';
        badge.textContent = '⭐ +' + data.points + (data.points === 1 ? ' bod' : (data.points < 5 ? ' body' : ' bodů'));
        anchor ? anchor.after(badge) : panel.prepend(badge);
    }

    const lvl = data.level;
    const box = document.createElement('div');
    if (data.levelup) {
        box.className = 'levelup-banner';
        box.innerHTML = '<div class="levelup-title">' + lvl.icon + ' Nový level ' + lvl.level + ' — ' + lvl.title + '!</div>' +
                        '<div class="levelup-sub">Celkem ' + lvl.points + ' bodů</div>';
    } else {
        box.className = 'levelup-banner';
        box.style.background = 'transparent';
        box.style.borderColor = 'var(--border)';
        box.style.color = 'var(--muted)';
        box.innerHTML = '<div class="levelup-sub">' + lvl.icon + ' Level ' + lvl.level + ' · ' + lvl.points + ' bodů' +
                        (lvl.next_level ? ' · ještě ' + lvl.remaining + ' do levelu ' + lvl.next_level : ' · nejvyšší level 🎉') +
                        '</div>';
    }
    const badge = panel.querySelector('.points-earned');
    badge ? badge.after(box) : (anchor ? anchor.after(box) : panel.prepend(box));

    // Splněné kroky výzvy a dokončené výzvy
    let anchorEl = box;
    const ch = data.challenge || {};
    (ch.challenges || []).forEach(title => {
        const el = document.createElement('div');
        el.className = 'challenge-earned';
        el.innerHTML = '<strong>🏆 Výzva splněna: ' + title + '</strong>';
        anchorEl.after(el);
        anchorEl = el;
    });
    (ch.steps || []).forEach(label => {
        const el = document.createElement('div');
        el.className = 'challenge-earned';
        el.innerHTML = '✔ Úkol z výzvy hotový: <strong>' + label + '</strong>';
        anchorEl.after(el);
        anchorEl = el;
    });

    // Nově získané odznaky — vkládáme za sebe, ať drží pořadí
    (data.achievements || []).forEach(a => {
        const el = document.createElement('div');
        el.className = 'achievement-earned';
        el.innerHTML = '<span class="ach-icon">' + a.icon + '</span>' +
                       '<span><strong>Nový odznak: ' + a.title + '</strong>' +
                       '<div class="ach-desc">' + a.desc + '</div></span>';
        anchorEl.after(el);
        anchorEl = el;
    });
}

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
