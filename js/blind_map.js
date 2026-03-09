// js/blind_map.js — Slepé mapy s Leaflet.js + reálnými GeoJSON hranicemi
(function () {
    const startBtn     = document.getElementById('mapStartBtn');
    const resetBtn     = document.getElementById('mapResetBtn');
    const hintEl       = document.getElementById('mapHint');
    const feedbackEl   = document.getElementById('mapFeedback');
    const progressBar  = document.getElementById('mapProgress');
    const resultsPanel = document.getElementById('mapResultsPanel');

    const isEurope = MAP_TYPE === 'europe';

    // Pro Evropu: MC tlačítka; pro ČR: textový vstup
    const choicesEl = isEurope ? document.getElementById('mapChoices') : null;
    const input     = isEurope ? null : document.getElementById('mapInput');

    // ── Leaflet mapa ──────────────────────────────────────────────────────
    const map = L.map('mapLeaflet', {
        center:           isEurope ? [54, 15] : [49.8, 15.5],
        zoom:             isEurope ? 4 : 7,
        zoomControl:      true,
        scrollWheelZoom:  false,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18,
    }).addTo(map);

    // ── Stav ──────────────────────────────────────────────────────────────
    let allFeatures = [], queue = [];
    let current = 0, correct = 0, wrong = 0, mistakes = [];
    let startTime = null, timerInt = null;
    let activeLayer = null, geoLayer = null;
    let answered = false; // blokuj dvojité kliknutí

    // ── Normalizace ───────────────────────────────────────────────────────
    function normalize(s) {
        return (s || '').trim().toLowerCase()
            .replace(/á/g,'a').replace(/é/g,'e').replace(/í/g,'i').replace(/ó/g,'o')
            .replace(/ú|ů/g,'u').replace(/ý/g,'y').replace(/č/g,'c').replace(/š/g,'s')
            .replace(/ž/g,'z').replace(/ř/g,'r').replace(/ň/g,'n').replace(/ě/g,'e')
            .replace(/ď/g,'d').replace(/ť/g,'t').replace(/\./g,'').replace(/-/g,' ');
    }

    function shuffle(arr) {
        const a = [...arr];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    // ── Styly ─────────────────────────────────────────────────────────────
    // fillOpacity: 1 = žádný mapový podklad neprosvitrá skrz barvu státu
    const styleDefault = { color: '#2563eb', weight: 1.5, fillColor: '#1e3a5f', fillOpacity: 0.85 };
    const styleActive  = { color: '#facc15', weight: 3,   fillColor: '#1d4ed8', fillOpacity: 1    };
    const styleCorrect = { color: '#4ade80', weight: 2.5, fillColor: '#14532d', fillOpacity: 1    };
    const styleWrong   = { color: '#f87171', weight: 2.5, fillColor: '#7f1d1d', fillOpacity: 1    };
    const styleDone    = { color: '#1e3a5f', weight: 1,   fillColor: '#0f2236', fillOpacity: 0.9  };

    // ── Generuj 3 možnosti (1 správná + 2 náhodné špatné) ────────────────
    function makeChoices(correctName) {
        const pool = allFeatures
            .map(f => f.properties.name)
            .filter(n => n !== correctName);
        const shuffled = shuffle(pool);
        const choices  = [correctName, shuffled[0], shuffled[1]];
        return shuffle(choices);
    }

    // ── Načtení GeoJSON ───────────────────────────────────────────────────
    async function loadGeoJSON() {
        hintEl.textContent = 'Načítám mapová data…';
        if (isEurope) {
            const res  = await fetch('https://cdn.jsdelivr.net/npm/world-atlas@2/countries-50m.json');
            const topo = await res.json();
            const geo  = topojson.feature(topo, topo.objects.countries);
            return geo.features
                .filter(f => EUROPE_ISO.hasOwnProperty(f.id))
                .map(f => ({ ...f, properties: { ...f.properties, name: EUROPE_ISO[f.id] } }));
        } else {
            // Kraje ČR — bundlováno lokálně v czech_regions.js, žádný fetch
            if (!window.CZECH_REGIONS_GEOJSON) {
                throw new Error('czech_regions.js nebylo načteno.');
            }
            return window.CZECH_REGIONS_GEOJSON.features.map(f => ({
                ...f,
                properties: { ...f.properties, name: f.properties.name }
            }));
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────
    async function initGame() {
        try {
            allFeatures = await loadGeoJSON();
            if (!allFeatures.length) { hintEl.textContent = '⚠ Žádná data.'; return; }

            geoLayer = L.geoJSON(allFeatures, {
                style: () => styleDefault,
                onEachFeature: (feature, layer) => {
                    layer.feature = feature;
                    // Tooltip pouze pro ČR kraje (Evropa má MC tlačítka)
                    if (!isEurope) {
                        layer.bindTooltip(feature.properties.name, {
                            permanent: false, direction: 'center', className: 'map-tooltip',
                        });
                    }
                    // žádný hover effect, žádný tooltip pro Evropu
                },
            }).addTo(map);

            map.fitBounds(geoLayer.getBounds(), { padding: [20, 20] });
            hintEl.textContent = isEurope
                ? `Načteno ${allFeatures.length} evropských států. Stiskni Začít!`
                : `Načteno ${allFeatures.length} krajů ČR. Stiskni Začít!`;
            dataReady = true;
            startBtn.textContent = 'Začít ▶';
        } catch (e) {
            hintEl.textContent = '⚠ Chyba při načítání dat: ' + e.message;
            startBtn.textContent = '↺ Zkusit znovu';
            startBtn.onclick = () => { dataReady = false; startBtn.textContent = 'Načítám…'; initGame(); };
        }
    }

    // ── Start ─────────────────────────────────────────────────────────────
    // POZOR: button záměrně NENÍ disabled — disabled blokuje klik i vizuálně
    // Stav načítání řešíme přes textContent a flag
    let dataReady = false;

    startBtn.addEventListener('click', () => {
        if (!dataReady) {
            hintEl.textContent = '⏳ Data se ještě načítají, počkej chvíli…';
            return;
        }
        startBtn.style.display = 'none';
        resetBtn.style.display = 'inline-block';
        if (input) { input.disabled = false; input.focus(); }
        if (choicesEl) choicesEl.style.display = 'flex';

        queue = shuffle([...Array(allFeatures.length).keys()]);
        current = correct = wrong = 0;
        mistakes = [];
        startTime = Date.now();
        timerInt  = setInterval(() => {
            document.getElementById('mapTime').textContent =
                Math.floor((Date.now() - startTime) / 1000);
        }, 500);
        showNext();
    });

    resetBtn.addEventListener('click', () => location.reload());

    // ── Zobraz další otázku ───────────────────────────────────────────────
    function showNext() {
        if (current >= queue.length) { finishGame(); return; }
        answered = false;

        const feature = allFeatures[queue[current]];

        // Resetuj předchozí aktivní stát (vrať na 'done' styl, ne default)
        if (activeLayer) activeLayer.setStyle(styleDone);

        // Najdi layer
        activeLayer = null;
        geoLayer.eachLayer(layer => {
            if (layer.feature === feature) activeLayer = layer;
        });
        if (activeLayer) {
            activeLayer.setStyle(styleActive);
            activeLayer.bringToFront();
            map.fitBounds(activeLayer.getBounds(), {
                padding:  isEurope ? [80, 80] : [60, 60],
                maxZoom:  isEurope ? 6 : 9,
                animate:  true,
                duration: 0.4,
            });
        }

        document.getElementById('mapRemain').textContent = queue.length - current;
        progressBar.style.width = Math.round(current / queue.length * 100) + '%';
        hintEl.textContent = `Jak se jmenuje zvýrazněný ${isEurope ? 'stát' : 'kraj'}?`;
        feedbackEl.textContent = '';
        feedbackEl.className   = 'math-feedback';

        if (isEurope) {
            // Vykresli 3 MC tlačítka
            renderChoices(feature.properties.name);
        } else {
            if (input) { input.value = ''; input.focus(); }
        }
    }

    // ── Render MC tlačítek ────────────────────────────────────────────────
    const LABELS = ['A', 'B', 'C'];
    function renderChoices(correctName) {
        const choices = makeChoices(correctName);
        choicesEl.innerHTML = '';
        choices.forEach((name, i) => {
            const btn = document.createElement('button');
            btn.className  = 'mc-choice-btn';
            btn.innerHTML  = `<span class="mc-key">${LABELS[i]}</span> ${name}`;
            btn.dataset.name = name;
            btn.addEventListener('click', () => pickAnswer(name, correctName, btn, choices));
            choicesEl.appendChild(btn);
        });
    }

    function pickAnswer(chosen, correctName, btn, choices) {
        if (answered) return;
        answered = true;

        const isOk = normalize(chosen) === normalize(correctName);

        // Obarvi tlačítka
        choicesEl.querySelectorAll('.mc-choice-btn').forEach(b => {
            if (b.dataset.name === correctName)  b.classList.add('mc-choice-correct');
            else if (b === btn && !isOk)         b.classList.add('mc-choice-wrong');
            b.disabled = true;
        });

        handleResult(isOk, correctName, chosen);
    }

    // ── Klávesy A/B/C (jen pro Evropu) ────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (!isEurope || answered) return;
        const idx = ['a','b','c'].indexOf(e.key.toLowerCase());
        if (idx === -1) return;
        const btn = choicesEl?.querySelectorAll('.mc-choice-btn')[idx];
        if (btn && !btn.disabled) btn.click();
    });

    // ── Zpracování textového vstupu (ČR kraje) ─────────────────────────────
    if (input) {
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            const val = input.value.trim();
            if (!val) return;
            const feature   = allFeatures[queue[current]];
            const expected  = feature.properties.name;
            const isOk      = normalize(val) === normalize(expected);
            handleResult(isOk, expected, val);
        });
    }

    // ── Společná logika výsledku ──────────────────────────────────────────
    function handleResult(isOk, correctName, typed) {
        if (activeLayer) {
            activeLayer.setStyle(isOk ? styleCorrect : styleWrong);
            if (!isOk && !isEurope) {
                // Pro ČR ukáž tooltip s názvem
                activeLayer.bindTooltip(correctName, {
                    permanent: true, direction: 'center', className: 'map-tooltip-reveal',
                }).openTooltip();
            }
        }

        if (isOk) {
            correct++;
            feedbackEl.textContent = '✔ ' + correctName;
            feedbackEl.className   = 'math-feedback feedback-ok';
        } else {
            wrong++;
            mistakes.push({ name: correctName, typed });
            feedbackEl.textContent = `✘ Správně: ${correctName}`;
            feedbackEl.className   = 'math-feedback feedback-err';
        }

        document.getElementById('mapScore').textContent  = correct;
        document.getElementById('mapErrors').textContent = wrong;
        current++;

        if (input) input.value = '';
        setTimeout(showNext, isOk ? 550 : 1300);
    }

    // ── Konec hry ─────────────────────────────────────────────────────────
    function finishGame() {
        clearInterval(timerInt);
        if (input) input.disabled = true;
        if (choicesEl) choicesEl.style.display = 'none';

        const elapsed  = (Date.now() - startTime) / 1000;
        const total    = correct + wrong;
        const accuracy = total > 0 ? Math.round(correct / total * 100) : 100;

        document.getElementById('mapResFinalScore').textContent    = `${correct}/${total}`;
        document.getElementById('mapResFinalErrors').textContent   = wrong;
        document.getElementById('mapResFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('mapResFinalTime').textContent     = Math.round(elapsed) + 's';

        const mistakesEl = document.getElementById('mapMistakes');
        if (mistakes.length > 0) {
            mistakesEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Co si zapamatovat:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.3rem 0;font-size:.875rem';
                row.innerHTML = `Správně: <strong style="color:var(--accent)">${m.name}</strong>`
                              + ` <span style="color:var(--danger);font-size:.8rem">(ty: ${m.typed})</span>`;
                mistakesEl.appendChild(row);
            });
        }

        resultsPanel.style.display = 'block';
        resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const fd = new FormData();
        fd.append('action','save'); fd.append('game_type','geography_map');
        fd.append('wpm', Math.round(correct / (elapsed / 60)));
        fd.append('accuracy', accuracy);
        fd.append('duration', Math.round(elapsed));
        fd.append('chars_typed', correct);
        fd.append('errors', wrong);
        fetch(SAVE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { document.getElementById('mapSaveStatus').textContent = d.ok ? '✔ Uloženo!' : ''; });
    }

    // ── Spusť ─────────────────────────────────────────────────────────────
    startBtn.textContent = 'Načítám…';
    initGame();
})();
