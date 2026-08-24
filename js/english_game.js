// js/english_game.js — Angličtina: slovíčka (výběr z možností i psaní)

(function () {
    const tasks         = [...EN_TASKS];
    const isInput       = EN_MODE === 'input';
    const startWrapper  = document.getElementById('startWrapper');
    const taskWrapper   = document.getElementById('taskWrapper');
    const startBtn      = document.getElementById('startBtn');
    const taskEl        = document.getElementById('enTask');
    const choicesEl     = document.getElementById('enChoices');
    const inputEl       = document.getElementById('enInput');
    const submitBtn     = document.getElementById('submitBtn');
    const feedbackEl    = document.getElementById('enFeedback');
    const dotsEl        = document.getElementById('enDots');
    const progressBar   = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0, mistakes = [];
    const answers = []; // pro chybovník — co dítěti šlo a co ne
    let startTime = null, timerInt = null, answered = false;

    // Stejné sjednocení zápisu jako na serveru (englishNorm v PHP): malá
    // písmena, bez diakritiky, bez interpunkce. Na mobilu se háčky píšou
    // těžko, tak je po dětech nechceme.
    const DIA = { 'á':'a','č':'c','ď':'d','é':'e','ě':'e','í':'i','ň':'n','ó':'o','ř':'r',
                  'š':'s','ť':'t','ú':'u','ů':'u','ý':'y','ž':'z' };
    function norm(s) {
        return String(s).toLowerCase()
            .replace(/[áčďéěíňóřšťúůýž]/g, ch => DIA[ch])
            .replace(/[^a-z0-9 ]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

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

    // Mobil: po otevření klávesnice prohlížeč odscrolluje k inputu a zadání
    // zmizí nahoře — proto ho vracíme do záběru (stejně jako v matematice).
    function keepTaskVisible() {
        setTimeout(() => taskEl.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300);
    }

    function showTask() {
        if (current >= tasks.length) { finishGame(); return; }
        answered = false;
        const t = tasks[current];

        taskEl.textContent = t.q;
        taskEl.classList.toggle('en-task-long', t.q.length > 14);
        feedbackEl.textContent = '';
        feedbackEl.className   = 'cz-feedback';

        if (isInput) {
            inputEl.value    = '';
            inputEl.disabled = false;
            inputEl.focus();
            keepTaskVisible();
        } else {
            choicesEl.innerHTML = '';
            t.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className   = 'en-choice-btn';
                btn.type        = 'button';
                btn.textContent = opt;
                btn.addEventListener('click', () => answer(opt, btn));
                choicesEl.appendChild(btn);
            });
        }

        progressBar.style.width = Math.round(current / tasks.length * 100) + '%';
        document.getElementById('statRemain').textContent = tasks.length - current;
        renderDots();
    }

    function answer(given, btn) {
        if (answered) return;
        const t = tasks[current];
        if (isInput && !given.trim()) return;
        answered = true;

        const isOk = t.accept.indexOf(norm(given)) !== -1;
        t._ok = isOk;

        if (isInput) {
            inputEl.disabled = true;
        } else {
            choicesEl.querySelectorAll('.en-choice-btn').forEach(b => {
                if (b.textContent === t.a)     b.classList.add('choice-correct');
                else if (b === btn && !isOk)   b.classList.add('choice-wrong');
                b.disabled = true;
            });
        }

        feedbackEl.innerHTML = (isOk ? '<strong class="feedback-ok">✔ Správně!</strong> '
                                     : '<strong class="feedback-err">✘ Chyba.</strong> ') +
                               '<span class="cz-hint">' + t.hint + '</span>';

        answers.push({ key: t.key, ok: isOk, prompt: t.q, answer: t.a, hint: t.hint });

        if (isOk) {
            correct++;
        } else {
            wrong++;
            mistakes.push({ hint: t.hint });
        }
        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;

        current++;
        setTimeout(showTask, isOk ? 900 : 2200); // u chyby delší pauza na přečtení
    }

    if (isInput) {
        submitBtn.addEventListener('click', () => answer(inputEl.value));
        inputEl.addEventListener('keydown', e => { if (e.key === 'Enter') answer(inputEl.value); });
        inputEl.addEventListener('focus', keepTaskVisible);
        window.visualViewport?.addEventListener('resize', () => {
            if (document.activeElement === inputEl) keepTaskVisible();
        });
    } else {
        // Na počítači jdou možnosti vybrat i klávesami 1–4
        document.addEventListener('keydown', e => {
            if (!startTime || answered) return;
            const i = parseInt(e.key, 10) - 1;
            const btns = choicesEl.querySelectorAll('.en-choice-btn');
            if (i >= 0 && i < btns.length) btns[i].click();
        });
    }

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

        const mEl = document.getElementById('enMistakes');
        if (mistakes.length) {
            mEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Slovíčka k zopakování:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.5rem 0;font-size:.9rem;color:var(--accent)';
                row.textContent = m.hint;
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
        fd.append('text_snippet', EN_SET);
        fd.append('theme', EN_THEME);
        fd.append('dir', EN_DIR);
        fd.append('answers', JSON.stringify(answers));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
