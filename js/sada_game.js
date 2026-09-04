// js/sada_game.js — hraní vlastní sady (slovíčka, pojmy, doplňovačky, čtení)

(function () {
    const tasks         = [...SET_TASKS];
    const startWrapper  = document.getElementById('startWrapper');
    const taskWrapper   = document.getElementById('taskWrapper');
    const startBtn      = document.getElementById('startBtn');
    const taskEl        = document.getElementById('setTask');
    const choicesEl     = document.getElementById('setChoices');
    const feedbackEl    = document.getElementById('setFeedback');
    const dotsEl        = document.getElementById('setDots');
    const progressBar   = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0, mistakes = [];
    const answers = [];  // pro chybovník
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

    // Doplňovačka má v textu podtržítko — ukaž ho jako mezeru, kam se doplňuje.
    // Ostatní formáty jsou prostě otázka.
    function renderTask(prompt, filled) {
        taskEl.innerHTML = '';
        const idx = prompt.indexOf('_');
        if (idx === -1) { taskEl.textContent = prompt; return; }

        const before = document.createElement('span');
        before.textContent = prompt.slice(0, idx);
        const gap = document.createElement('span');
        gap.className   = 'cz-gap' + (filled ? ' cz-gap-filled' : '');
        gap.textContent = filled || '?';
        const after = document.createElement('span');
        after.textContent = prompt.slice(idx + 1);
        taskEl.append(before, gap, after);
    }

    function showTask() {
        if (current >= tasks.length) { finishGame(); return; }
        answered = false;
        const t = tasks[current];

        renderTask(t.prompt, null);
        feedbackEl.textContent = '';
        feedbackEl.className   = 'cz-feedback';
        choicesEl.innerHTML    = '';

        t.options.forEach(opt => {
            const btn = document.createElement('button');
            // Odpovědi bývají celá slova nebo věty, ne jedno písmeno
            btn.className   = 'cz-choice-btn cz-choice-wide';
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

        renderTask(t.prompt, t.correct);

        choicesEl.querySelectorAll('.cz-choice-btn').forEach(b => {
            if (b.dataset.val === t.correct) b.classList.add('choice-correct');
            else if (b === btn && !isOk)      b.classList.add('choice-wrong');
            b.disabled = true;
        });

        feedbackEl.innerHTML =
            (isOk ? '<strong class="feedback-ok">✔ Správně!</strong> '
                  : '<strong class="feedback-err">✘ Správně je: ' + t.correct + '</strong> ') +
            (t.hint ? '<span class="cz-hint">' + t.hint + '</span>' : '');

        const shown = t.prompt.includes('_') ? t.prompt.replace('_', t.correct)
                                             : t.prompt + ' → ' + t.correct;
        answers.push({ key: t.key, ok: isOk, prompt: t.prompt, answer: t.correct, hint: t.hint });

        if (isOk) {
            correct++;
        } else {
            wrong++;
            mistakes.push({ text: shown, hint: t.hint });
        }
        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;

        current++;
        setTimeout(showTask, isOk ? 900 : 2200);  // u chyby delší pauza na přečtení
    }

    // Čísla 1–9 vybírají možnost — rychlejší než mířit na tlačítko
    document.addEventListener('keydown', e => {
        if (!startTime || answered) return;
        const n = parseInt(e.key, 10);
        if (!n) return;
        const btn = choicesEl.querySelectorAll('.cz-choice-btn')[n - 1];
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

        const mEl = document.getElementById('setMistakes');
        if (mistakes.length) {
            mEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Co si zapamatovat:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.5rem 0;font-size:.875rem';
                row.innerHTML = '<strong style="color:var(--accent)"></strong>';
                row.querySelector('strong').textContent = m.text;
                if (m.hint) {
                    const h = document.createElement('div');
                    h.style.cssText = 'color:var(--muted);font-size:.8rem';
                    h.textContent = m.hint;
                    row.appendChild(h);
                }
                mEl.appendChild(row);
            });
        }

        gameContainer.style.display = 'none';
        resultsPanel.style.display  = 'block';
        const passage = document.getElementById('setPassage');
        if (passage) passage.style.display = 'none';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('id', SET_ID);
        fd.append('dir', SET_DIR);
        fd.append('wpm', wpm);
        fd.append('accuracy', accuracy);
        fd.append('duration', Math.round(elapsed));
        fd.append('chars_typed', correct);
        fd.append('errors', wrong);
        fd.append('text_snippet', SET_NAME);
        fd.append('answers', JSON.stringify(answers));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
