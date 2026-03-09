// js/math_game.js

(function () {
    const examples   = [...MATH_EXAMPLES];
    const input      = document.getElementById('mathInput');
    const startBtn   = document.getElementById('startBtn');
    const questionEl = document.getElementById('mathQuestion');
    const feedbackEl = document.getElementById('mathFeedback');
    const dotsEl     = document.getElementById('mathDots');
    const progressBar = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0;
    let startTime = null, timerInt = null;

    // Vykreslí tečky průběhu
    function renderDots() {
        dotsEl.innerHTML = '';
        examples.forEach((_, i) => {
            const d = document.createElement('span');
            d.className = 'math-dot' +
                (i < current ? (examples[i]._ok ? ' dot-ok' : ' dot-err') : '') +
                (i === current ? ' dot-current' : '');
            dotsEl.appendChild(d);
        });
    }

    function showQuestion() {
        if (current >= examples.length) { finishGame(); return; }
        questionEl.textContent  = examples[current].q;
        feedbackEl.textContent  = '';
        feedbackEl.className    = 'math-feedback';
        input.value             = '';
        input.focus();
        progressBar.style.width = Math.round(current / examples.length * 100) + '%';
        document.getElementById('statRemain').textContent = examples.length - current;
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
        if (e.key === 'Enter') {
            const val = input.value.trim();
            if (!val) return;
            const isOk = val === examples[current].a;
            examples[current]._ok = isOk;

            feedbackEl.textContent = isOk ? '✔ Správně!' : `✘ Správně: ${examples[current].a}`;
            feedbackEl.className   = 'math-feedback ' + (isOk ? 'feedback-ok' : 'feedback-err');

            if (isOk) correct++; else wrong++;
            document.getElementById('statScore').textContent  = correct;
            document.getElementById('statErrors').textContent = wrong;

            current++;
            setTimeout(showQuestion, isOk ? 400 : 900);
        }
    });

    function finishGame() {
        clearInterval(timerInt);
        input.disabled = true;
        const elapsed  = (Date.now() - startTime) / 1000;
        const accuracy = Math.round(correct / examples.length * 100);
        // WPM ekvivalent: každé číslo = 1 "slovo"
        const wpm = Math.round(correct / (elapsed / 60));

        document.getElementById('resFinalScore').textContent    = correct + '/' + examples.length;
        document.getElementById('resFinalErrors').textContent   = wrong;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';

        gameContainer.style.display = 'none';
        resultsPanel.style.display  = 'block';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('wpm', wpm);
        fd.append('accuracy', accuracy);
        fd.append('duration', Math.round(elapsed));
        fd.append('chars_typed', correct);
        fd.append('errors', wrong);
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { document.getElementById('saveStatus').textContent = d.ok ? '✔ Uloženo!' : ''; });
    }
})();
