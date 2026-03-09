// js/timed_game.js — Časový závod

(function () {
    const input       = document.getElementById('typingInput');
    const wordsEl     = document.getElementById('timedWords');
    const startBtn    = document.getElementById('startBtn');
    const resetBtn    = document.getElementById('resetBtn');
    const statCountdown = document.getElementById('statCountdown');
    const statWpm     = document.getElementById('statWpm');
    const statWords   = document.getElementById('statWords');
    const statErrors  = document.getElementById('statErrors');
    const progressBar = document.getElementById('progressBar');
    const resultsPanel = document.getElementById('resultsPanel');
    const gameContainer = document.getElementById('gameContainer');

    let wordList     = [];
    let currentIndex = 0;
    let correctWords = 0;
    let errorWords   = 0;
    let startTime    = null;
    let timeLeft     = DURATION;
    let timerInt     = null;
    let running      = false;
    let typedChars   = 0;
    let errorChars   = 0;

    function shuffle(arr) {
        const a = [...arr];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    function buildWordList() {
        wordList = shuffle(WORD_POOL).slice(0, 200);
    }

    function renderWords() {
        wordsEl.innerHTML = '';
        const show = Math.min(currentIndex + 40, wordList.length);
        for (let i = currentIndex; i < show; i++) {
            const span = document.createElement('span');
            span.className = 'timed-word' + (i === currentIndex ? ' current' : '');
            span.textContent = wordList[i];
            span.dataset.index = i;
            wordsEl.appendChild(span);
        }
    }

    function initGame() {
        buildWordList();
        currentIndex = 0;
        correctWords = 0;
        errorWords   = 0;
        typedChars   = 0;
        errorChars   = 0;
        timeLeft     = DURATION;
        running      = false;
        clearInterval(timerInt);

        statCountdown.textContent = DURATION;
        statWpm.textContent       = '0';
        statWords.textContent     = '0';
        statErrors.textContent    = '0';
        progressBar.style.width   = '100%';

        input.value    = '';
        input.disabled = true;
        input.classList.remove('input-error');

        startBtn.style.display = 'inline-block';
        resetBtn.style.display = 'none';
        resultsPanel.style.display  = 'none';
        gameContainer.style.display = 'block';

        renderWords();
    }

    function startGame() {
        input.disabled = false;
        input.focus();
        startBtn.style.display = 'none';
        resetBtn.style.display = 'inline-block';

        startTime = Date.now();
        running   = true;

        timerInt = setInterval(() => {
            timeLeft--;
            statCountdown.textContent = timeLeft;
            progressBar.style.width   = (timeLeft / DURATION * 100) + '%';

            const elapsed = (Date.now() - startTime) / 1000 / 60;
            statWpm.textContent = elapsed > 0 ? Math.round(correctWords / elapsed) : 0;

            if (timeLeft <= 0) finishGame();
        }, 1000);
    }

    input.addEventListener('input', function () {
        if (!running) return;

        const typed   = input.value.trim();
        const current = wordList[currentIndex];

        // Mezera = potvrzení slova
        if (input.value.endsWith(' ')) {
            const isCorrect = typed === current;
            typedChars += current.length;

            const wordEl = wordsEl.querySelector('.current');
            if (wordEl) {
                wordEl.classList.remove('current');
                wordEl.classList.add(isCorrect ? 'word-correct' : 'word-error');
            }

            if (isCorrect) {
                correctWords++;
                input.classList.remove('input-error');
            } else {
                errorWords++;
                errorChars += Math.abs(typed.length - current.length) + 1;
                input.classList.add('input-error');
                setTimeout(() => input.classList.remove('input-error'), 300);
            }

            currentIndex++;
            statWords.textContent  = correctWords;
            statErrors.textContent = errorWords;
            input.value = '';

            if (currentIndex >= wordList.length) buildWordList();
            renderWords();

            // Scroll pokud je potřeba
            const next = wordsEl.querySelector('.current');
            if (next) next.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            return;
        }

        // Živá validace barvy
        const isOk = current.startsWith(typed);
        input.classList.toggle('input-error', !isOk && typed.length > 0);
    });

    function finishGame() {
        clearInterval(timerInt);
        running = false;
        input.disabled = true;

        const elapsed = DURATION / 60;
        const wpm     = Math.round(correctWords / elapsed);
        const total   = correctWords + errorWords;
        const accuracy = total > 0 ? Math.round((correctWords / total) * 100) : 100;

        document.getElementById('resFinalWpm').textContent      = wpm;
        document.getElementById('resFinalWords').textContent    = correctWords;
        document.getElementById('resFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('resFinalErrors').textContent   = errorWords;

        gameContainer.style.display  = 'none';
        resultsPanel.style.display   = 'block';

        saveResult({ wpm, accuracy, errors: errorWords });
    }

    async function saveResult({ wpm, accuracy, errors }) {
        const status = document.getElementById('saveStatus');
        status.textContent = 'Ukládám výsledek...';
        try {
            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('wpm', wpm);
            fd.append('accuracy', accuracy);
            fd.append('duration', DURATION);
            fd.append('chars_typed', typedChars);
            fd.append('errors', errors);
            fd.append('text_snippet', wordList.slice(0, 10).join(' '));
            const r = await fetch(SAVE_URL, { method: 'POST', body: fd });
            const d = await r.json();
            status.textContent = d.ok ? '✔ Výsledek uložen!' : '⚠ Nepodařilo se uložit.';
        } catch { status.textContent = '⚠ Chyba při ukládání.'; }
    }

    startBtn.addEventListener('click', startGame);
    resetBtn.addEventListener('click', initGame);
    initGame();
})();
