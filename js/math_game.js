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
    const hintEl     = document.getElementById('mathHint');   // maraton: u každého příkladu jiný tvar odpovědi
    const progressBar = document.getElementById('progressBar');
    const gameContainer = document.getElementById('gameContainer');
    const resultsPanel  = document.getElementById('resultsPanel');

    let current = 0, correct = 0, wrong = 0;
    // Časový limit (sestava maratonu): odpočítává se a po vypršení kolo končí
    const TIME_LIMIT = typeof MATH_TIME_LIMIT !== 'undefined' ? Number(MATH_TIME_LIMIT) : 0;
    let timedOut = false;
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
        if (hintEl) hintEl.textContent = examples[current].hint || '';
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

    // Číselník na obrazovce: na dotykovém zařízení místo systémové klávesnice,
    // která zabere půl displeje a odscrolluje příklad pryč. Na počítači s myší
    // se nestaví a píše se normálně. inputmode="none" nechá pole aktivní
    // (Enter, fyzická klávesnice), ale klávesnici na mobilu neotevře.
    const keypad = buildKeypad();
    function buildKeypad() {
        if (!window.matchMedia('(pointer: coarse)').matches) return null;
        input.setAttribute('inputmode', 'none');
        const extra    = input.dataset.keys || '';
        const specials = [];
        if (extra.includes(',')) specials.push({ label: ',',  ins: ',' });
        if (extra.includes('/')) specials.push({ label: '/',  ins: '/' });
        if (extra.includes('zb')) specials.push({ label: 'zb', ins: ' zb ' });

        // čtyři sloupce: číslice vlevo, vpravo mazání a speciální klávesy, dole OK
        const rows = [['7', '8', '9'], ['4', '5', '6'], ['1', '2', '3'], ['0']];
        const keys = [];
        rows.forEach((row, r) => {
            row.forEach(k => keys.push({ label: k, ins: k }));
            if (r === 3) { keys.push(specials.shift() || { label: '', dead: true }); keys.push({ label: '✓ OK', act: 'ok', wide: true }); return; }
            keys.push(r === 0 ? { label: '⌫', act: 'back' } : (specials.shift() || { label: '', dead: true }));
        });
        // kdyby speciálních kláves bylo víc, než je volných míst, přidá se řádek
        while (specials.length) keys.push(specials.shift());

        const pad = document.createElement('div');
        pad.className = 'math-keypad';
        pad.hidden = true;
        keys.forEach(k => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'math-key' + (k.wide ? ' math-key-ok' : '') + (k.act === 'back' ? ' math-key-back' : '') + (k.dead ? ' math-key-dead' : '');
            b.textContent = k.label;
            if (k.dead) { b.disabled = true; b.tabIndex = -1; }
            // pointerdown + preventDefault: tlačítko nebere fokus a pole zůstává aktivní
            b.addEventListener('pointerdown', e => e.preventDefault());
            b.addEventListener('click', () => {
                if (input.disabled) return;
                if (k.act === 'ok') { submitAnswer(); }
                else if (k.act === 'back') { input.value = input.value.replace(/( zb |.)$/, ''); }
                else if (k.ins) {
                    // dvě mezery ani dva zbytky za sebou nedávají smysl
                    if (k.ins === ' zb ' && / zb /.test(input.value)) return;
                    input.value = (k.ins === ' zb ' ? input.value.trimEnd() : input.value) + k.ins;
                }
                input.focus({ preventScroll: true });
            });
            pad.appendChild(b);
        });
        document.querySelector('.typing-input-wrapper').after(pad);
        return pad;
    }

    startBtn.addEventListener('click', () => {
        startBtn.style.display = 'none';
        if (submitBtn) submitBtn.style.display = keypad ? 'none' : '';   // číselník má vlastní OK
        if (keypad) keypad.hidden = false;
        input.disabled = false;
        input.focus();
        startTime = Date.now();
        timerInt  = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            if (TIME_LIMIT > 0) {
                document.getElementById('statTime').textContent = Math.max(0, TIME_LIMIT - elapsed);
                if (elapsed >= TIME_LIMIT) { timedOut = true; finishGame(); }
            } else {
                document.getElementById('statTime').textContent = elapsed;
            }
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
                       prompt: examples[current].q, answer: examples[current].a, given: val });

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
        current = Math.min(current, examples.length);
        if (submitBtn) submitBtn.style.display = 'none';
        if (keypad) keypad.hidden = true;
        if (finishGame.done) return;   // limit i poslední odpověď můžou přijít naráz
        finishGame.done = true;
        const elapsed    = timedOut ? TIME_LIMIT : (Date.now() - startTime) / 1000;
        const unanswered = examples.length - current;
        const accuracy   = Math.round(correct / examples.length * 100);
        // WPM ekvivalent: každé číslo = 1 "slovo"
        const wpm = Math.round(correct / (elapsed / 60));

        document.getElementById('resFinalScore').textContent    = correct + '/' + examples.length;
        document.getElementById('resFinalErrors').textContent   = wrong;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalTime').textContent     = Math.round(elapsed) + 's';
        if (timedOut) {
            const note = document.createElement('p');
            note.className = 'marathon-result';
            note.textContent = '⏰ Čas vypršel — nestihnuto ' + unanswered + ' z ' + examples.length + ' příkladů (počítají se jako chyby).';
            document.querySelector('#resultsPanel .results-stats').after(note);
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
        fd.append('unanswered', unanswered);
        fd.append('text_snippet', typeof MATH_SET !== 'undefined' ? MATH_SET : 'matematika');
        fd.append('topic', MATH_TOPIC);
        fd.append('variant', MATH_VARIANT);
        fd.append('answers', JSON.stringify(answers));
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => renderReward(d, document.getElementById('saveStatus')));
    }
})();
