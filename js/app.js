// js/app.js — obecné UI pomocné funkce
document.addEventListener('DOMContentLoaded', function () {
    // Animace vstupu karet
    document.querySelectorAll('.stat-card, .game-card').forEach((el, i) => {
        el.style.animationDelay = (i * 0.07) + 's';
        el.classList.add('fade-in-up');
    });
});
