// js/blind_game.js — Slepý režim

(function () {
    const chars      = document.querySelectorAll('#typingText .tchar');
    const input      = document.getElementById('typingInput');
    const startBtn   = document.getElementById('startBtn');
    const resetBtn   = document.getElementById('resetBtn');
    const statTime   = document.getElementById('statTime');
    const statProg   = document.getElementById('statProgress');
    const progressBar = document.getElementById('progressBar');
    const resultsPanel = document.getElementById('resultsPanel');
    const gameContainer = document.getElementById('gameContainer');

    let charIndex = 0, errors = 0, totalTyped = 0;
    let startTime = null, timerInt = null, running = false;
    let typedChars = []; // zaznamenáváme co hráč napsal

    chars[0]?.classList.add('cursor');

    startBtn.addEventListener('click', () => {
        input.disabled = false;
        input.focus();
        startBtn.style.display = 'none';
        resetBtn.style.display = 'inline-block';
    });

    input.addEventListener('keydown', e => { if (e.key === 'Backspace') e.preventDefault(); });

    input.addEventListener('input', function () {
        if (!running) {
            running   = true;
            startTime = Date.now();
            timerInt  = setInterval(() => {
                statTime.textContent = Math.floor((Date.now() - startTime) / 1000);
            }, 500);
        }

        // Mobilní klávesnice můžou vložit víc znaků najednou — zpracuj všechny
        const typedStr = input.value;
        input.value = '';
        if (!typedStr) return;

        for (const typed of typedStr) {
            if (charIndex >= chars.length) break;
            const expected = GAME_TEXT[charIndex];
            const correct  = typed === expected;

            typedChars.push({ typed, expected, correct });
            if (!correct) errors++;
            totalTyped++;
            charIndex++;
        }

        // Slepý: pouze posouváme kurzor, BEZ barevné zpětné vazby
        chars.forEach(c => c.classList.remove('cursor'));
        if (charIndex < chars.length) chars[charIndex].classList.add('cursor');

        const pct = Math.round(charIndex / chars.length * 100);
        progressBar.style.width  = pct + '%';
        statProg.textContent     = pct;

        if (charIndex >= chars.length) finishGame();
    });

    function finishGame() {
        clearInterval(timerInt);
        running = false;
        input.disabled = true;

        const elapsed  = (Date.now() - startTime) / 1000;
        const wpm      = Math.round((totalTyped / 5) / (elapsed / 60));
        const accuracy = totalTyped > 0 ? Math.round((totalTyped - errors) / totalTyped * 100) : 100;

        // Odhali chyby — zpětně obarvi text
        const revealEl = document.getElementById('blindReveal');
        revealEl.innerHTML = '<div class="blind-reveal-title">Co jsi napsal:</div>';
        const line = document.createElement('div');
        line.className = 'blind-reveal-text';
        typedChars.forEach(({ typed, correct }) => {
            const span = document.createElement('span');
            span.className = correct ? 'tchar correct' : 'tchar error';
            span.textContent = typed === ' ' ? '\u00a0' : typed;
            line.appendChild(span);
        });
        revealEl.appendChild(line);

        document.getElementById('resFinalWpm').textContent      = wpm;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';
        document.getElementById('resFinalErrors').textContent   = errors;

        gameContainer.style.display  = 'none';
        resultsPanel.style.display   = 'block';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('wpm', wpm);
        fd.append('accuracy', accuracy);
        fd.append('duration', Math.round(elapsed));
        fd.append('chars_typed', totalTyped);
        fd.append('errors', errors);
        fd.append('text_snippet', GAME_TEXT);
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }

    resetBtn.addEventListener('click', () => location.reload());
})();
