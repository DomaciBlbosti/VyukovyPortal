// js/czech_game.js — Čeština: doplňování i/y

(function () {
    const tasks        = [...CZ_TASKS];
    const startWrapper = document.getElementById('startWrapper');
    const taskWrapper  = document.getElementById('taskWrapper');
    const startBtn     = document.getElementById('startBtn');
    const taskEl       = document.getElementById('czTask');
    const choicesEl    = document.getElementById('czChoices');
    const feedbackEl   = document.getElementById('czFeedback');
    const dotsEl       = document.getElementById('czDots');
    const progressBar  = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0, mistakes = [];
    let startTime = null, timerInt = null, answered = false;

    function renderDots() {
        dotsEl.innerHTML = '';
        tasks.forEach((_, i) => {
            const d = document.createElement('span');
            d.className = 'math-dot' +
                (i < current ? (tasks[i]._ok ? ' dot-ok' : ' dot-err') : '') +
                (i === current ? ' dot-current' : '');
            dotsEl.appendChild(d);
        });
    }

    // Text s podtržítkem → zvýrazněná mezera, kam patří písmeno
    function renderTask(text, filled) {
        taskEl.innerHTML = '';
        const idx = text.indexOf('_');
        const before = document.createElement('span');
        before.textContent = text.slice(0, idx);
        const gap = document.createElement('span');
        gap.className = 'cz-gap' + (filled ? ' cz-gap-filled' : '');
        gap.textContent = filled || '?';
        const after = document.createElement('span');
        after.textContent = text.slice(idx + 1);
        taskEl.append(before, gap, after);
    }

    function showTask() {
        if (current >= tasks.length) { finishGame(); return; }
        answered = false;
        const t = tasks[current];

        renderTask(t.text, null);
        feedbackEl.textContent = '';
        feedbackEl.className   = 'cz-feedback';
        choicesEl.innerHTML    = '';

        t.options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className   = 'cz-choice-btn';
            btn.textContent = opt;
            btn.dataset.val = opt;
            btn.addEventListener('click', () => pickAnswer(opt, btn));
            choicesEl.appendChild(btn);
        });

        progressBar.style.width = Math.round(current / tasks.length * 100) + '%';
        document.getElementById('statRemain').textContent = tasks.length - current;
        renderDots();
    }

    function pickAnswer(chosen, btn) {
        if (answered) return;
        answered = true;

        const t    = tasks[current];
        const isOk = chosen === t.correct;
        t._ok = isOk;

        // Doplň písmeno do textu, ať je vidět výsledný tvar
        renderTask(t.text, t.correct);

        choicesEl.querySelectorAll('.cz-choice-btn').forEach(b => {
            if (b.dataset.val === t.correct) b.classList.add('choice-correct');
            else if (b === btn && !isOk)      b.classList.add('choice-wrong');
            b.disabled = true;
        });

        feedbackEl.innerHTML = (isOk ? '<strong class="feedback-ok">✔ Správně!</strong> ' : '<strong class="feedback-err">✘ Chyba.</strong> ') +
                               '<span class="cz-hint">' + t.hint + '</span>';

        if (isOk) {
            correct++;
        } else {
            wrong++;
            mistakes.push({ text: t.text.replace('_', t.correct), hint: t.hint });
        }
        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;

        current++;
        setTimeout(showTask, isOk ? 900 : 2200); // u chyby delší pauza na přečtení
    }

    // Klávesy: i, y, í, ý, a
    document.addEventListener('keydown', e => {
        if (!startTime || answered) return;
        const key = e.key.toLowerCase();
        const btn = [...choicesEl.querySelectorAll('.cz-choice-btn')]
            .find(b => b.dataset.val === key);
        if (btn) btn.click();
    });

    startBtn.addEventListener('click', () => {
        startWrapper.style.display = 'none';
        taskWrapper.style.display  = 'block';
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('statTime').textContent = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showTask();
    });

    function finishGame() {
        clearInterval(timerInt);
        const elapsed  = (Date.now() - startTime) / 1000;
        const accuracy = Math.round(correct / tasks.length * 100);
        const wpm      = Math.round(correct / (elapsed / 60));

        document.getElementById('resFinalScore').textContent    = correct + '/' + tasks.length;
        document.getElementById('resFinalErrors').textContent   = wrong;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';

        const mEl = document.getElementById('czMistakes');
        if (mistakes.length) {
            mEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Co si zapamatovat:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.5rem 0;font-size:.875rem';
                row.innerHTML = '<strong style="color:var(--accent)">' + m.text + '</strong>' +
                                '<div style="color:var(--muted);font-size:.8rem">' + m.hint + '</div>';
                mEl.appendChild(row);
            });
        }

        gameContainer.style.display = 'none';
        resultsPanel.style.display  = 'block';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('wpm', wpm);
        fd.append('accuracy', accuracy);
        fd.append('duration', Math.round(elapsed));
        fd.append('chars_typed', correct);
        fd.append('errors', wrong);
        fd.append('text_snippet', CZ_SET);
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
