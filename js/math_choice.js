// js/math_choice.js — Matematika: výběr výsledku ze 6 možností

(function () {
    const examples   = [...MATH_EXAMPLES];
    const startBtn   = document.getElementById('startBtn');
    const questionEl = document.getElementById('mathQuestion');
    const feedbackEl = document.getElementById('mathFeedback');
    const dotsEl     = document.getElementById('mathDots');
    const gridEl     = document.getElementById('choiceGrid');
    const progressBar = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');
    const questionWrapper = document.querySelector('.math-question-wrapper');

    let current = 0, correct = 0, wrong = 0;
    let startTime = null, timerInt = null, answered = false;
    const answers = []; // pro chybovník — co dítěti šlo a co ne

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
        answered = false;
        const ex = examples[current];

        questionEl.textContent = ex.q;
        // dlouhá zadání (NSD, dělitelé) zmenši, ať se vejdou na jeden řádek
        questionEl.classList.toggle('math-question-long', questionEl.textContent.length > 16);
        feedbackEl.textContent = '';
        feedbackEl.className   = 'math-feedback';
        gridEl.innerHTML       = '';

        ex.choices.forEach((choice) => {
            const btn = document.createElement('button');
            btn.className   = 'math-choice-btn';
            btn.textContent = choice;
            btn.dataset.val = choice;
            btn.addEventListener('click', () => pickAnswer(choice, btn));
            gridEl.appendChild(btn);
        });

        progressBar.style.width = Math.round(current / examples.length * 100) + '%';
        document.getElementById('statRemain').textContent = examples.length - current;
        renderDots();
        questionWrapper?.scrollIntoView({ block: 'nearest' });
    }

    function pickAnswer(chosen, btn) {
        if (answered) return;
        answered = true;

        const answer = examples[current].a;
        const isOk   = chosen === answer;
        examples[current]._ok = isOk;

        gridEl.querySelectorAll('.math-choice-btn').forEach(b => {
            if (b.dataset.val === answer) b.classList.add('choice-correct');
            else if (b === btn && !isOk)  b.classList.add('choice-wrong');
            b.disabled = true;
        });

        feedbackEl.textContent = isOk ? '✔ Správně!' : `✘ Správně: ${answer}`;
        feedbackEl.className   = 'math-feedback ' + (isOk ? 'feedback-ok' : 'feedback-err');

        answers.push({ key: examples[current].key, ok: isOk,
                       prompt: examples[current].q, answer: answer });

        if (isOk) correct++; else wrong++;
        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;

        current++;
        setTimeout(showQuestion, isOk ? 500 : 1100);
    }

    // Klávesy 1–6
    document.addEventListener('keydown', e => {
        if (!startTime || answered) return;
        const idx = ['1','2','3','4','5','6'].indexOf(e.key);
        if (idx === -1) return;
        const btn = gridEl.querySelectorAll('.math-choice-btn')[idx];
        if (btn) btn.click();
    });

    startBtn.addEventListener('click', () => {
        startBtn.style.display = 'none';
        gridEl.style.display   = '';
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('statTime').textContent = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showQuestion();
    });

    function finishGame() {
        clearInterval(timerInt);
        const elapsed  = (Date.now() - startTime) / 1000;
        const accuracy = Math.round(correct / examples.length * 100);
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
        fd.append('text_snippet', typeof MATH_SET !== 'undefined' ? MATH_SET : 'matematika');
        fd.append('topic', MATH_TOPIC);
        fd.append('variant', MATH_VARIANT);
        fd.append('answers', JSON.stringify(answers));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
