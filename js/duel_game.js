// js/duel_game.js — Souboj hráčů

(function () {
    const results = { p1: {}, p2: {} };

    // ── Render textu ────────────────────────────────────────────────────────
    function renderText(containerId, text) {
        const el = document.getElementById(containerId);
        el.innerHTML = '';
        [...text].forEach(ch => {
            const span = document.createElement('span');
            span.className = 'tchar';
            span.textContent = ch === ' ' ? '\u00a0' : ch;
            el.appendChild(span);
        });
        return el.querySelectorAll('.tchar');
    }

    // ── Generická hra pro jednoho hráče ─────────────────────────────────────
    function createPlayer(prefix, text, onFinish) {
        const chars      = renderText(prefix + 'Text', text);
        const input      = document.getElementById(prefix + 'Input');
        const startBtn   = document.getElementById(prefix + 'StartBtn');
        const wpmEl      = document.getElementById(prefix + 'Wpm');
        const accEl      = document.getElementById(prefix + 'Accuracy');
        const timeEl     = document.getElementById(prefix + 'Time');
        const errEl      = document.getElementById(prefix + 'Errors');
        const progress   = document.getElementById(prefix + 'Progress');

        let charIndex = 0, errors = 0, totalTyped = 0;
        let startTime = null, timerInt = null, running = false;

        chars[0]?.classList.add('cursor');

        startBtn.addEventListener('click', () => {
            input.disabled = false;
            input.focus();
            startBtn.style.display = 'none';
        });

        input.addEventListener('keydown', e => { if (e.key === 'Backspace') e.preventDefault(); });

        input.addEventListener('input', function () {
            if (!running) {
                running   = true;
                startTime = Date.now();
                timerInt  = setInterval(() => {
                    timeEl.textContent = Math.floor((Date.now() - startTime) / 1000);
                    updateStats();
                }, 500);
            }

            const typed = input.value.slice(-1);
            if (!typed) return;

            if (typed === text[charIndex]) {
                chars[charIndex].classList.replace('cursor', 'correct');
            } else {
                chars[charIndex].classList.add('error');
                errors++;
            }
            totalTyped++;
            charIndex++;
            input.value = '';
            chars.forEach(c => c.classList.remove('cursor'));
            if (charIndex < chars.length) chars[charIndex].classList.add('cursor');

            progress.style.width = Math.round(charIndex / chars.length * 100) + '%';
            updateStats();
            if (charIndex >= chars.length) finish();
        });

        function updateStats() {
            const mins = startTime ? (Date.now() - startTime) / 1000 / 60 : 0;
            wpmEl.textContent = mins > 0 ? Math.round((totalTyped / 5) / mins) : 0;
            accEl.textContent = totalTyped > 0 ? Math.round((totalTyped - errors) / totalTyped * 100) : 100;
            errEl.textContent = errors;
        }

        function finish() {
            clearInterval(timerInt);
            input.disabled = true;
            const elapsed  = (Date.now() - startTime) / 1000;
            const wpm      = Math.round((totalTyped / 5) / (elapsed / 60));
            const accuracy = totalTyped > 0 ? Math.round((totalTyped - errors) / totalTyped * 100) : 100;
            onFinish({ wpm, accuracy, elapsed, errors, totalTyped });
        }
    }

    // ── Orchestrace ──────────────────────────────────────────────────────────
    document.getElementById('startDuelBtn').addEventListener('click', () => {
        const p1Name = document.getElementById('p1name').value;
        const p2Name = document.getElementById('p2name').value;
        document.getElementById('p1Label').textContent = p1Name;
        document.getElementById('p2Label').textContent = p2Name;

        document.getElementById('duelSetup').style.display  = 'none';
        document.getElementById('p1Container').style.display = 'block';

        createPlayer('p1', DUEL_TEXT, (r) => {
            results.p1 = { ...r, name: p1Name };

            document.getElementById('p1Container').style.display    = 'none';
            document.getElementById('duelIntermission').style.display = 'block';
            document.getElementById('p1DoneName').textContent = p1Name;
            document.getElementById('p1DoneWpm').textContent  = r.wpm;
            document.getElementById('p1DoneAcc').textContent  = r.accuracy + '%';

            document.getElementById('startP2Btn').addEventListener('click', () => {
                document.getElementById('duelIntermission').style.display = 'none';
                document.getElementById('p2Container').style.display      = 'block';
                document.getElementById('p2Label').textContent = p2Name;

                createPlayer('p2', DUEL_TEXT, (r2) => {
                    results.p2 = { ...r2, name: p2Name };
                    showFinalResults(p1Name, p2Name);
                });
            }, { once: true });
        });
    });

    function showFinalResults(p1Name, p2Name) {
        document.getElementById('p2Container').style.display  = 'none';
        document.getElementById('duelResults').style.display  = 'block';

        const r1 = results.p1, r2 = results.p2;
        document.getElementById('p1ResultName').textContent = p1Name;
        document.getElementById('p1ResultWpm').textContent  = r1.wpm;
        document.getElementById('p1ResultAcc').textContent  = r1.accuracy + '% přesnost';
        document.getElementById('p2ResultName').textContent = p2Name;
        document.getElementById('p2ResultWpm').textContent  = r2.wpm;
        document.getElementById('p2ResultAcc').textContent  = r2.accuracy + '% přesnost';

        const winner = r1.wpm >= r2.wpm ? p1Name : p2Name;
        document.getElementById('duelWinnerTitle').textContent = '🏆 Vítěz: ' + winner + '!';
        document.getElementById(r1.wpm >= r2.wpm ? 'p1ResultCard' : 'p2ResultCard')
                .classList.add('winner-card');

        // Ulož výsledky
        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('p1_name', p1Name);
        fd.append('p1_wpm', r1.wpm);
        fd.append('p1_accuracy', r1.accuracy);
        fd.append('p1_duration', Math.round(r1.elapsed));
        fd.append('p1_chars', r1.totalTyped);
        fd.append('p1_errors', r1.errors);
        fd.append('p2_name', p2Name);
        fd.append('p2_wpm', r2.wpm);
        fd.append('p2_accuracy', r2.accuracy);
        fd.append('p2_duration', Math.round(r2.elapsed));
        fd.append('p2_chars', r2.totalTyped);
        fd.append('p2_errors', r2.errors);
        fd.append('text_snippet', DUEL_TEXT.substring(0, 200));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { document.getElementById('saveStatus').textContent = d.ok ? '✔ Výsledky uloženy!' : ''; });
    }
})();
