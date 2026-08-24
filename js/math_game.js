// js/math_game.js

(function () {
    const examples   = [...MATH_EXAMPLES];

    // Děti píšou desetinnou tečku i čárku, zbytek jako „3 zb 2" i „3 zb. 2".
    // Porovnáváme tedy až po sjednocení zápisu.
    function normalize(s) {
        return String(s).trim().toLowerCase()
            .replace(/\./g, ',')          // 4.5 → 4,5 ; zb. → zb,
            .replace(/,(?=\s|$)/g, '')    // odstraň čárku na konci (zb,)
            .replace(/\s+/g, ' ')
            .replace(/\s*\/\s*/g, '/'); // 3 / 4 → 3/4
    }
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
    const answers = []; // pro chybovník — co dítěti šlo a co ne
    const questionWrapper = document.querySelector('.math-question-wrapper');

    // Mobil: po otevření klávesnice prohlížeč odscrolluje k inputu a příklad
    // zmizí nahoře — po fokusu i každé otázce vrať příklad do záběru.
    function keepQuestionVisible() {
        setTimeout(() => questionWrapper?.scrollIntoView({ block: 'start', behavior: 'smooth' }), 300);
    }

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
        // dlouhá zadání (NSD, dělitelé) zmenši, ať se vejdou na jeden řádek
        questionEl.classList.toggle('math-question-long', questionEl.textContent.length > 16);
        feedbackEl.textContent  = '';
        feedbackEl.className    = 'math-feedback';
        input.value             = '';
        input.focus();
        progressBar.style.width = Math.round(current / examples.length * 100) + '%';
        document.getElementById('statRemain').textContent = examples.length - current;
        renderDots();
        keepQuestionVisible();
    }

    const submitBtn = document.getElementById('submitBtn');

    startBtn.addEventListener('click', () => {
        startBtn.style.display = 'none';
        if (submitBtn) submitBtn.style.display = '';
        input.disabled = false;
        input.focus();
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('statTime').textContent = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showQuestion();
    });

    function submitAnswer() {
        if (current >= examples.length) return;
        const val = input.value.trim();
        if (!val) return;
        const isOk = normalize(val) === normalize(examples[current].a);
        examples[current]._ok = isOk;

        feedbackEl.textContent = isOk ? '✔ Správně!' : `✘ Správně: ${examples[current].a}`;
        feedbackEl.className   = 'math-feedback ' + (isOk ? 'feedback-ok' : 'feedback-err');

        answers.push({ key: examples[current].key, ok: isOk,
                       prompt: examples[current].q, answer: examples[current].a });

        if (isOk) correct++; else wrong++;
        document.getElementById('statScore').textContent  = correct;
        document.getElementById('statErrors').textContent = wrong;

        current++;
        setTimeout(showQuestion, isOk ? 400 : 900);
    }

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') submitAnswer();
    });
    input.addEventListener('focus', keepQuestionVisible);
    // Otevření klávesnice zmenší viewport až po fokusu — zareaguj i na to
    window.visualViewport?.addEventListener('resize', () => {
        if (document.activeElement === input) keepQuestionVisible();
    });
    submitBtn?.addEventListener('click', () => { submitAnswer(); input.focus(); });

    function finishGame() {
        clearInterval(timerInt);
        input.disabled = true;
        if (submitBtn) submitBtn.style.display = 'none';
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
        fd.append('text_snippet', typeof MATH_SET !== 'undefined' ? MATH_SET : 'matematika');
        fd.append('topic', MATH_TOPIC);
        fd.append('variant', MATH_VARIANT);
        fd.append('answers', JSON.stringify(answers));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
