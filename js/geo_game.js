// js/geo_game.js — Zeměpis otázky (aktualizováno)
(function () {
    const questions  = [...GEO_QUESTIONS];
    const input      = document.getElementById('geoInput');
    const startBtn   = document.getElementById('startBtn');
    const questionEl = document.getElementById('geoQuestion');
    const feedbackEl = document.getElementById('geoFeedback');
    const dotsEl     = document.getElementById('geoDots');
    const progressBar = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0, mistakes = [];
    let startTime = null, timerInt = null;

    function normalize(s) {
        return s.trim().toLowerCase()
            .replace(/á/g,'a').replace(/é/g,'e').replace(/í/g,'i').replace(/ó/g,'o')
            .replace(/ú|ů/g,'u').replace(/ý/g,'y').replace(/č/g,'c').replace(/š/g,'s')
            .replace(/ž/g,'z').replace(/ř/g,'r').replace(/ň/g,'n').replace(/ě/g,'e')
            .replace(/ď/g,'d').replace(/ť/g,'t');
    }

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
        questionEl.textContent  = questions[current].q;
        feedbackEl.textContent  = '';
        feedbackEl.className    = 'math-feedback';
        input.value             = '';
        input.focus();
        progressBar.style.width = Math.round(current / questions.length * 100) + '%';
        document.getElementById('statRemain').textContent = questions.length - current;
        renderDots();
    }

    startBtn.addEventListener('click', () => {
        startBtn.style.display = 'none';
        input.disabled = false;
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('statTime').textContent = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showQuestion();
    });

    input.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        const val = input.value.trim();
        if (!val) return;

        const expected = questions[current].a;
        const isOk = normalize(val) === normalize(expected);
        questions[current]._ok = isOk;

        feedbackEl.textContent = isOk ? `✔ Správně!` : `✘ Správně: ${expected}`;
        feedbackEl.className   = 'math-feedback ' + (isOk ? 'feedback-ok' : 'feedback-err');

        if (isOk) correct++;
        else { wrong++; mistakes.push({ q: questions[current].q, correct: expected, typed: val }); }

        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;
        current++;
        setTimeout(showQuestion, isOk ? 500 : 1200);
    });

    function finishGame() {
        clearInterval(timerInt);
        input.disabled = true;
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
                row.style.cssText = 'margin:.3rem 0;font-size:.875rem';
                row.innerHTML = `<span style="color:var(--muted)">${m.q}</span> <strong style="color:var(--accent)">${m.correct}</strong> <span style="color:var(--danger);font-size:.8rem">(ty: ${m.typed})</span>`;
                mistakesEl.appendChild(row);
            });
        }

        gameContainer.style.display = 'none';
        resultsPanel.style.display  = 'block';

        const fd = new FormData();
        fd.append('action','save'); fd.append('game_type','geography');
        fd.append('wpm',wpm); fd.append('accuracy',accuracy);
        fd.append('duration',Math.round(elapsed)); fd.append('chars_typed',correct);
        fd.append('errors',wrong);
        fetch(SAVE_URL,{method:'POST',body:fd}).then(r=>r.json())
          .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
