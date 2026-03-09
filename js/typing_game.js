// js/typing_game.js — Logika klasické hry

(function () {
    const textChars  = document.querySelectorAll('#typingText .tchar');
    const input      = document.getElementById('typingInput');
    const startBtn   = document.getElementById('startBtn');
    const resetBtn   = document.getElementById('resetBtn');
    const playAgainBtn = document.getElementById('playAgainBtn');

    const statWpm      = document.getElementById('statWpm');
    const statAccuracy = document.getElementById('statAccuracy');
    const statTime     = document.getElementById('statTime');
    const statErrors   = document.getElementById('statErrors');
    const progressBar  = document.getElementById('progressBar');

    const resultsPanel = document.getElementById('resultsPanel');
    const gameContainer = document.getElementById('gameContainer');

    let startTime    = null;
    let timerInterval = null;
    let charIndex    = 0;
    let errors       = 0;
    let totalTyped   = 0;
    let isRunning    = false;

    // ─── Inicializace ───────────────────────────────────
    function initGame() {
        charIndex  = 0;
        errors     = 0;
        totalTyped = 0;
        isRunning  = false;
        startTime  = null;

        clearInterval(timerInterval);

        textChars.forEach(c => c.className = 'tchar');
        textChars[0]?.classList.add('cursor');

        input.value   = '';
        input.disabled = true;

        statWpm.textContent      = '0';
        statAccuracy.textContent = '100';
        statTime.textContent     = '0';
        statErrors.textContent   = '0';
        progressBar.style.width  = '0%';

        startBtn.style.display  = 'inline-block';
        resetBtn.style.display  = 'none';
        resultsPanel.style.display = 'none';
        gameContainer.style.display = 'block';
        document.getElementById('saveStatus').textContent = '';
    }

    // ─── Start ──────────────────────────────────────────
    function startGame() {
        input.disabled = false;
        input.focus();
        startBtn.style.display = 'none';
        resetBtn.style.display = 'inline-block';
    }

    // ─── Psaní ──────────────────────────────────────────
    input.addEventListener('input', function () {
        if (!isRunning) {
            isRunning = true;
            startTime = Date.now();
            timerInterval = setInterval(updateTimer, 500);
        }

        const typedChar = input.value.slice(-1);
        const expected  = GAME_TEXT[charIndex];

        if (typedChar === undefined) return; // smazání

        if (typedChar === expected) {
            textChars[charIndex].classList.remove('cursor', 'error');
            textChars[charIndex].classList.add('correct');
        } else {
            textChars[charIndex].classList.add('error');
            errors++;
        }

        totalTyped++;
        charIndex++;
        input.value = '';

        // Posun kurzoru
        textChars.forEach(c => c.classList.remove('cursor'));
        if (charIndex < textChars.length) {
            textChars[charIndex].classList.add('cursor');
        }

        updateProgress();
        updateStats();

        if (charIndex >= textChars.length) {
            finishGame();
        }
    });

    // Backspace — přeskočíme (žádný návrat)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace') e.preventDefault();
    });

    // ─── Aktualizace UI ─────────────────────────────────
    function updateTimer() {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        statTime.textContent = elapsed;
    }

    function updateProgress() {
        const pct = Math.round((charIndex / textChars.length) * 100);
        progressBar.style.width = pct + '%';
    }

    function updateStats() {
        const elapsed = startTime ? (Date.now() - startTime) / 1000 / 60 : 0;
        const words   = totalTyped / 5;
        const wpm     = elapsed > 0 ? Math.round(words / elapsed) : 0;
        const accuracy = totalTyped > 0
            ? Math.round(((totalTyped - errors) / totalTyped) * 100)
            : 100;

        statWpm.textContent      = wpm;
        statAccuracy.textContent = accuracy;
        statErrors.textContent   = errors;
    }

    // ─── Konec hry ──────────────────────────────────────
    function finishGame() {
        clearInterval(timerInterval);
        isRunning = false;
        input.disabled = true;

        const elapsed     = (Date.now() - startTime) / 1000;
        const elapsedMin  = elapsed / 60;
        const words       = totalTyped / 5;
        const wpm         = Math.round(words / elapsedMin);
        const accuracy    = totalTyped > 0
            ? Math.round(((totalTyped - errors) / totalTyped) * 100)
            : 100;

        // Zobraz výsledky
        document.getElementById('resFinalWpm').textContent      = wpm;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';
        document.getElementById('resFinalErrors').textContent   = errors;

        progressBar.style.width = '100%';
        gameContainer.style.display   = 'none';
        resultsPanel.style.display = 'block';

        // Uložit do DB
        saveResult({ wpm, accuracy, elapsed, errors });
    }

    async function saveResult({ wpm, accuracy, elapsed, errors }) {
        const status = document.getElementById('saveStatus');
        status.textContent = 'Ukládám výsledek...';

        try {
            const fd = new FormData();
            fd.append('action',       'save');
            fd.append('wpm',          wpm);
            fd.append('accuracy',     accuracy);
            fd.append('duration',     Math.round(elapsed));
            fd.append('chars_typed',  totalTyped);
            fd.append('errors',       errors);
            fd.append('text_snippet', GAME_TEXT.substring(0, 200));

            const resp = await fetch(SAVE_URL, { method: 'POST', body: fd });
            const data = await resp.json();

            status.textContent = data.ok ? '✔ Výsledek uložen!' : '⚠ Nepodařilo se uložit.';
        } catch (e) {
            status.textContent = '⚠ Chyba při ukládání.';
        }
    }

    // ─── Tlačítka ───────────────────────────────────────
    startBtn.addEventListener('click',    startGame);
    resetBtn.addEventListener('click',    initGame);
    playAgainBtn?.addEventListener('click', () => {
        initGame();
        // Stránka se může refreshnout pro nový text
        location.reload();
    });

    // ─── Start ──────────────────────────────────────────
    initGame();
})();
