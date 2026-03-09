// js/geo_mc.js — Zeměpis: multiple choice 3 možnosti
(function () {
    const questions   = [...GEO_QUESTIONS];
    const startWrapper = document.getElementById('startWrapper');
    const mcWrapper    = document.getElementById('mcWrapper');
    const startBtn     = document.getElementById('startBtn');
    const questionEl   = document.getElementById('mcQuestion');
    const choicesEl    = document.getElementById('mcChoices');
    const feedbackEl   = document.getElementById('mcFeedback');
    const dotsEl       = document.getElementById('mcDots');
    const progressBar  = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0, mistakes = [];
    let startTime = null, timerInt = null, answered = false;

    const LABELS = ['A', 'B', 'C'];

    function renderDots() {
        dotsEl.innerHTML = '';
        questions.forEach((_, i) => {
            const d = document.createElement('span');
            d.className = 'math-dot' +
                (i < current ? (questions[i]._ok ? ' dot-ok' : ' dot-err') : '') +
                (i === current ? ' dot-current' : '');
            dotsEl.appendChild(d);
        });
    }

    function showQuestion() {
        if (current >= questions.length) { finishGame(); return; }
        answered = false;
        const q = questions[current];

        questionEl.textContent = q.q;
        feedbackEl.textContent = '';
        feedbackEl.className   = 'mc-feedback';
        choicesEl.innerHTML    = '';

        q.choices.forEach((choice, i) => {
            const btn = document.createElement('button');
            btn.className    = 'mc-choice-btn';
            btn.dataset.idx  = i;
            btn.innerHTML    = `<span class="mc-key">${LABELS[i]}</span> ${choice}`;
            btn.addEventListener('click', () => pickAnswer(choice, q.a, btn));
            choicesEl.appendChild(btn);
        });

        progressBar.style.width = Math.round(current / questions.length * 100) + '%';
        document.getElementById('statRemain').textContent = questions.length - current;
        renderDots();
    }

    function pickAnswer(chosen, correct_ans, btn) {
        if (answered) return;
        answered = true;

        const isOk = chosen === correct_ans;
        questions[current]._ok = isOk;

        // Obarvi všechny buttony
        choicesEl.querySelectorAll('.mc-choice-btn').forEach(b => {
            const bText = b.textContent.replace(/^[ABC]\s*/, '').trim();
            if (bText === correct_ans) b.classList.add('mc-choice-correct');
            else if (b === btn && !isOk) b.classList.add('mc-choice-wrong');
            b.disabled = true;
        });

        if (isOk) {
            correct++;
            feedbackEl.textContent = '✔ Správně!';
            feedbackEl.className   = 'mc-feedback feedback-ok';
        } else {
            wrong++;
            mistakes.push({ q: questions[current].q, correct: correct_ans, typed: chosen });
            feedbackEl.textContent = `✘ Správně: ${correct_ans}`;
            feedbackEl.className   = 'mc-feedback feedback-err';
        }

        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;
        current++;
        setTimeout(showQuestion, isOk ? 600 : 1200);
    }

    // Klávesy A/B/C
    document.addEventListener('keydown', e => {
        if (!mcWrapper || mcWrapper.style.display === 'none') return;
        if (answered) return;
        const idx = ['a','b','c'].indexOf(e.key.toLowerCase());
        if (idx === -1) return;
        const btn = choicesEl.querySelectorAll('.mc-choice-btn')[idx];
        if (btn) btn.click();
    });

    startBtn.addEventListener('click', () => {
        startWrapper.style.display = 'none';
        mcWrapper.style.display    = 'block';
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('statTime').textContent = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showQuestion();
    });

    function finishGame() {
        clearInterval(timerInt);
        const elapsed  = (Date.now() - startTime) / 1000;
        const accuracy = Math.round(correct / questions.length * 100);
        const wpm      = Math.round(correct / (elapsed / 60));

        document.getElementById('resFinalScore').textContent    = correct + '/' + questions.length;
        document.getElementById('resFinalErrors').textContent   = wrong;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';

        const mistakesEl = document.getElementById('geoMistakes');
        if (mistakes.length > 0) {
            mistakesEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Co si zapamatovat:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.35rem 0;font-size:.875rem';
                row.innerHTML = `<span style="color:var(--muted)">${m.q}</span> <strong style="color:var(--accent)">${m.correct}</strong><span style="color:var(--danger);font-size:.8rem"> (ty: ${m.typed})</span>`;
                mistakesEl.appendChild(row);
            });
        }

        gameContainer.style.display = 'none';
        resultsPanel.style.display  = 'block';

        const fd = new FormData();
        fd.append('action','save'); fd.append('game_type','geography');
        fd.append('wpm',wpm); fd.append('accuracy',accuracy);
        fd.append('duration',Math.round(elapsed));
        fd.append('chars_typed',correct); fd.append('errors',wrong);
        fetch(SAVE_URL,{method:'POST',body:fd}).then(r=>r.json())
          .then(d=>{document.getElementById('saveStatus').textContent=d.ok?'✔ Uloženo!':'';});
    }
})();
