// blind_map.js — Slepé mapy v2, MC A/B/C, žádné tooltips
(function () {

    // ── DOM refs ──────────────────────────────────────────────────────────
    const startBtn     = document.getElementById('mapStartBtn');
    const resetBtn     = document.getElementById('mapResetBtn');
    const hintEl       = document.getElementById('mapHint');
    const feedbackEl   = document.getElementById('mapFeedback');
    const progressBar  = document.getElementById('mapProgress');
    const resultsPanel = document.getElementById('mapResultsPanel');
    const choicesEl    = document.getElementById('mapChoices');

    // ── Tile vrstva — CartoDB Dark bez popisků (všechny mapy) ───────────
    const tileUrl  = 'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png';
    const tileAttr = '&copy; <a href="https://carto.com/">CARTO</a> | &copy; OpenStreetMap';

    const mapCenters = {
        europe:    [54, 15],
        czech:     [49.8, 15.5],
        world:     [20, 10],
        cities_cz: [49.8, 15.5],
        rivers_eu: [52, 14],
        seas:      [20, 20],
    };
    const mapZooms = {
        europe: 4, czech: 7, world: 2, cities_cz: 7, rivers_eu: 4, seas: 2,
    };

    const map = L.map('mapLeaflet', {
        center:          mapCenters[MAP_TYPE] || [50, 15],
        zoom:            mapZooms[MAP_TYPE]   || 4,
        zoomControl:     true,
        scrollWheelZoom: false,
        worldCopyJump:   false,
    });

    L.tileLayer(tileUrl, {
        attribution: tileAttr,
        maxZoom: 18,
        subdomains: 'abcd',
    }).addTo(map);

    // ── Stav ──────────────────────────────────────────────────────────────
    let allFeatures = [], queue = [];
    let current = 0, correct = 0, wrong = 0, mistakes = [];
    let startTime = null, timerInt = null;
    let activeLayer = null, geoLayer = null;
    let answered = false, dataReady = false;

    // ── Styly ─────────────────────────────────────────────────────────────
    // seas mají průhlednou výplň (vidět podkladovou mapu)
    const SEA_FILL_OPACITY = 0.55;
    const S = {
        default: { color: '#3b82f6', weight: 1.5,  fillColor: '#1e3a5f', fillOpacity: 1 },
        active:  { color: '#facc15', weight: 3,     fillColor: '#1d4ed8', fillOpacity: 1 },
        correct: { color: '#4ade80', weight: 2.5,   fillColor: '#14532d', fillOpacity: 1 },
        wrong:   { color: '#f87171', weight: 2.5,   fillColor: '#7f1d1d', fillOpacity: 1 },
        done:    { color: '#1e3a5f', weight: 1,     fillColor: '#0d1b2e', fillOpacity: 1 },
        // Linie (řeky)
        lineDefault: { color: '#3b82f6', weight: 2.5, opacity: 0.8 },
        lineActive:  { color: '#facc15', weight: 6,   opacity: 1   },
        lineCorrect: { color: '#4ade80', weight: 5,   opacity: 1   },
        lineWrong:   { color: '#f87171', weight: 5,   opacity: 1   },
        lineDone:    { color: '#1e3a8a', weight: 1.5, opacity: 0.3 },
        // Moře polygony — průhledné, vidět tvary pevnin pod tím
        seaDefault: { color: '#60a5fa', weight: 2,   fillColor: '#1e4080', fillOpacity: SEA_FILL_OPACITY },
        seaActive:  { color: '#facc15', weight: 3.5, fillColor: '#1d4ed8', fillOpacity: 0.75 },
        seaCorrect: { color: '#4ade80', weight: 3,   fillColor: '#14532d', fillOpacity: 0.75 },
        seaWrong:   { color: '#f87171', weight: 3,   fillColor: '#7f1d1d', fillOpacity: 0.75 },
        seaDone:    { color: '#1e3a5f', weight: 1,   fillColor: '#0d1b2e', fillOpacity: 0.35 },
    };

    function circleStyle(state, isSea) {
        const base = isSea
            ? { default: { color: '#60a5fa', fillColor: '#1e4080', radius: 28 },
                active:  { color: '#facc15', fillColor: '#1d4ed8', radius: 34 },
                correct: { color: '#4ade80', fillColor: '#14532d', radius: 30 },
                wrong:   { color: '#f87171', fillColor: '#7f1d1d', radius: 30 },
                done:    { color: '#1e3a5f', fillColor: '#0d1b2e', radius: 20 } }
            : { default: { color: '#3b82f6', fillColor: '#1e3a5f', radius: 9  },
                active:  { color: '#facc15', fillColor: '#1d4ed8', radius: 13 },
                correct: { color: '#4ade80', fillColor: '#14532d', radius: 11 },
                wrong:   { color: '#f87171', fillColor: '#7f1d1d', radius: 11 },
                done:    { color: '#1e3a5f', fillColor: '#0f2236', radius: 6  } };
        return { ...base[state], weight: 2, fillOpacity: isSea ? 0.55 : 1, opacity: 1 };
    }

    function geomType(feature) {
        const t = (feature.geometry?.type || '');
        if (t === 'Point' || t === 'MultiPoint')           return 'point';
        if (t === 'LineString' || t === 'MultiLineString') return 'line';
        return 'polygon';
    }

    function styleFor(state, feature) {
        const t   = geomType(feature);
        const cap = state[0].toUpperCase() + state.slice(1);
        if (t === 'line')              return S['line' + cap];
        if (MAP_TYPE === 'seas')       return S['sea'  + cap];
        return S[state];
    }

    // ── Normalize ─────────────────────────────────────────────────────────
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

    // ── Overlay krajů ČR pro cities_cz: stejný styl jako v režimu "czech" ──
    // Kraje jsou jen vizuální podklad — interactive: false, žádné eventy
    async function loadCzechOverlay() {
        try {
            const url  = 'https://cdn.jsdelivr.net/npm/@highcharts/map-collection@2/countries/cz/cz-all.topo.json';
            const res  = await fetch(url);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const topo = await res.json();
            const key  = Object.keys(topo.objects)[0];
            const geo  = topojson.feature(topo, topo.objects[key]);
            L.geoJSON(geo.features, {
                style: () => ({
                    color:       '#2563eb',   // modrá hranice krajů
                    weight:      1.5,
                    fillColor:   '#1e3a5f',   // sytá tmavě modrá výplň
                    fillOpacity: 1,
                    interactive: false,       // nelze klikat, žádné eventy
                }),
                onEachFeature: () => {},
            }).addTo(map);
        } catch(e) {
            console.warn('CZ overlay chyba:', e);
        }
    }

    // ── Oprava geometrie pro Leaflet (antimeridián) ──────────────────────
    // world-atlas ukládá Rusko/USA s lng mimo -180..180 → čára přes mapu.
    // fixRing aplikujeme POUZE na ringy které skutečně přechází přes ±180°
    // (max-min lng > 300°), jinak bychom rozbili Austrálii a podobné.
    function ringNeedsAntiFix(ring) {
        let minL = Infinity, maxL = -Infinity;
        for (const c of ring) { if (c[0] < minL) minL = c[0]; if (c[0] > maxL) maxL = c[0]; }
        return (maxL - minL) > 300;
    }
    function fixRing(ring) {
        if (!ring.length || !ringNeedsAntiFix(ring)) return ring;
        const out = [[ring[0][0], ring[0][1]]];
        for (let i = 1; i < ring.length; i++) {
            let lng = ring[i][0];
            const prev = out[i - 1][0];
            while (lng - prev >  180) lng -= 360;
            while (prev - lng >  180) lng += 360;
            out.push([lng, ring[i][1]]);
        }
        return out;
    }
    function fixPoly(poly)  { return poly.map(fixRing); }
    function fixGeometry(geom) {
        if (!geom) return geom;
        if (geom.type === 'Polygon')
            return { ...geom, coordinates: fixPoly(geom.coordinates) };
        if (geom.type === 'MultiPolygon')
            return { ...geom, coordinates: geom.coordinates.map(fixPoly) };
        return geom;
    }

    // ── Načtení dat ───────────────────────────────────────────────────────
    async function loadGeoJSON() {
        hintEl.textContent = 'Načítám mapová data…';

        if (MAP_TYPE === 'europe') {
            const res  = await fetch('https://cdn.jsdelivr.net/npm/world-atlas@2/countries-50m.json');
            const topo = await res.json();
            const geo  = topojson.feature(topo, topo.objects.countries);
            return geo.features
                .filter(f => EUROPE_ISO.hasOwnProperty(String(f.id)))
                .map(f => ({
                    ...f,
                    properties: { ...f.properties, name: EUROPE_ISO[String(f.id)] },
                    geometry: fixGeometry(f.geometry),
                }));
        }

        if (MAP_TYPE === 'world') {
            const res  = await fetch('https://cdn.jsdelivr.net/npm/world-atlas@2/countries-50m.json');
            const topo = await res.json();
            const geo  = topojson.feature(topo, topo.objects.countries);
            return geo.features
                .filter(f => WORLD_ISO.hasOwnProperty(String(f.id)))
                .map(f => ({
                    ...f,
                    properties: { ...f.properties, name: WORLD_ISO[String(f.id)] },
                    geometry: fixGeometry(f.geometry),
                }));
        }

        if (MAP_TYPE === 'czech') {
            const url  = 'https://cdn.jsdelivr.net/npm/@highcharts/map-collection@2/countries/cz/cz-all.topo.json';
            const res  = await fetch(url);
            const topo = await res.json();
            const key  = Object.keys(topo.objects)[0];
            const geo  = topojson.feature(topo, topo.objects[key]);
            const czNames = {
                'Prague':'Praha','Central Bohemian':'Středočeský kraj',
                'South Bohemian':'Jihočeský kraj','Plzeň':'Plzeňský kraj',
                'Karlovy Vary':'Karlovarský kraj','Ústí nad Labem':'Ústecký kraj',
                'Liberec':'Liberecký kraj','Hradec Králové':'Královéhradecký kraj',
                'Pardubice':'Pardubický kraj','Vysočina':'Kraj Vysočina',
                'South Moravian':'Jihomoravský kraj','Olomouc':'Olomoucký kraj',
                'Zlín':'Zlínský kraj','Moravian-Silesian':'Moravskoslezský kraj',
            };
            return geo.features
                .filter(f => f.properties?.name)
                .map(f => {
                    let name = f.properties.name;
                    for (const [en, cs] of Object.entries(czNames)) {
                        if (name.includes(en)) { name = cs; break; }
                    }
                    return { ...f, properties: { ...f.properties, name } };
                });
        }

        if (MAP_TYPE === 'cities_cz') {
            await loadCzechOverlay();
            return MAP_DATA.features;
        }

        if (MAP_TYPE === 'rivers_eu') {
            // Zkus načíst z CDN Natural Earth, jinak použij záložní inline data
            try {
                const res = await fetch('https://cdn.jsdelivr.net/npm/natural-earth-geojson@1.1.0/ne_50m_rivers_lake_centerlines.geojson');
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const geo = await res.json();
                const wanted = Object.keys(RIVERS_NAMES);
                const found = {};
                geo.features.forEach(f => {
                    const n = f.properties?.name || f.properties?.NAME || '';
                    const match = wanted.find(w => n === w || n.toLowerCase().includes(w.toLowerCase()));
                    if (match && !found[match]) {
                        found[match] = { ...f, properties: { ...f.properties, name: RIVERS_NAMES[match] } };
                    }
                });
                const result = Object.values(found);
                if (result.length >= 5) return result;
                throw new Error('Málo řek z CDN');
            } catch (e) {
                console.warn('CDN řeky selhaly, použiji inline data:', e.message);
                return MAP_DATA.features;
            }
        }

        if (MAP_TYPE === 'seas') return MAP_DATA.features;

        throw new Error('Neznámý typ mapy: ' + MAP_TYPE);
    }

    // ── Vykresli vrstvu ───────────────────────────────────────────────────
    function buildLayer(features) {
        return L.geoJSON(features, {
            style: (f) => styleFor('default', f),
            pointToLayer: (f, latlng) => {
                // Oceány jsou Point → velký CircleMarker
                if (MAP_TYPE === 'seas') return L.circleMarker(latlng, circleStyle('default', true));
                return L.circleMarker(latlng, circleStyle('default', false));
            },
            onEachFeature: () => {},
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────
    async function initGame() {
        try {
            allFeatures = await loadGeoJSON();
            if (!allFeatures.length) throw new Error('Žádná data.');

            geoLayer = buildLayer(allFeatures).addTo(map);

            // Speciální handling Russia (přesahuje antimeridián → nepovoluji fitBounds)
            if (MAP_TYPE !== 'world') {
                map.fitBounds(geoLayer.getBounds(), { padding: [20, 20] });
            }

            hintEl.textContent = 'Načteno ' + allFeatures.length + ' položek. Stiskni Začít!';
            dataReady = true;
            startBtn.textContent = 'Začít ▶';
        } catch (e) {
            hintEl.textContent = '⚠ Chyba: ' + e.message;
            startBtn.textContent = '↺ Zkusit znovu';
            startBtn.onclick = () => { dataReady = false; startBtn.textContent = 'Načítám…'; initGame(); };
        }
    }

    // ── Start ─────────────────────────────────────────────────────────────
    startBtn.textContent = 'Načítám…';
    startBtn.addEventListener('click', () => {
        if (!dataReady) { hintEl.textContent = '⏳ Načítám data…'; return; }
        startBtn.style.display  = 'none';
        resetBtn.style.display  = 'inline-block';
        choicesEl.style.display = 'flex';

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

    // ── Zobraz otázku ─────────────────────────────────────────────────────
    function setLayerStyle(layer, state) {
        const f   = layer.feature;
        const t   = geomType(f);
        const sea = (MAP_TYPE === 'seas');
        if (t === 'point') layer.setStyle(circleStyle(state, sea));
        else               layer.setStyle(styleFor(state, f));
    }

    function showNext() {
        if (current >= queue.length) { finishGame(); return; }
        answered = false;

        const feature = allFeatures[queue[current]];
        if (activeLayer) setLayerStyle(activeLayer, 'done');

        activeLayer = null;
        geoLayer.eachLayer(l => { if (l.feature === feature) activeLayer = l; });

        if (activeLayer) {
            setLayerStyle(activeLayer, 'active');
            activeLayer.bringToFront();

            // Zaměř mapu na aktivní prvek (s rozumným max zoomem)
            const t = geomType(feature);
            const maxZooms = { europe: 6, czech: 9, world: 5, cities_cz: 10, rivers_eu: 7, seas: 4 };
            const pads     = { europe: [80,80], czech: [60,60], world: [60,60], cities_cz: [80,80], rivers_eu: [60,60], seas: [40,40] };

            try {
                if (t === 'point') {
                    const ll  = activeLayer.getLatLng();
                    const sea = (MAP_TYPE === 'seas');
                    if (sea) {
                        // Oceány - oddalíme na zoom 3 aby byl vidět kontext
                        map.setView([ll.lat, ll.lng], 3, { animate: true, duration: 0.5 });
                    } else {
                        const b = L.latLngBounds([[ll.lat-0.8,ll.lng-1.2],[ll.lat+0.8,ll.lng+1.2]]);
                        map.fitBounds(b, { padding: pads[MAP_TYPE]||[60,60], maxZoom: maxZooms[MAP_TYPE]||6, animate:true, duration:0.4 });
                    }
                } else {
                    const bounds = activeLayer.getBounds();
                    const lngSpan = bounds.getEast() - bounds.getWest();
                    if (lngSpan > 100) {
                        map.setView(bounds.getCenter(), 2, { animate: true });
                    } else {
                        map.fitBounds(bounds, { padding: pads[MAP_TYPE]||[60,60], maxZoom: maxZooms[MAP_TYPE]||6, animate:true, duration:0.4 });
                    }
                }
            } catch(e) { /* bounds error */ }
        }

        document.getElementById('mapRemain').textContent = queue.length - current;
        progressBar.style.width = Math.round(current / queue.length * 100) + '%';
        hintEl.textContent = MAP_HINT;
        feedbackEl.textContent = '';
        feedbackEl.className   = 'math-feedback';
        renderChoices(feature.properties.name);
    }

    // ── MC tlačítka ───────────────────────────────────────────────────────
    const LABELS = ['A', 'B', 'C'];

    function makeChoices(correctName) {
        const pool = shuffle(
            allFeatures.map(f => f.properties.name).filter(n => n !== correctName)
        );
        return shuffle([correctName, pool[0], pool[1]]);
    }

    function renderChoices(correctName) {
        const choices = makeChoices(correctName);
        choicesEl.innerHTML = '';
        choices.forEach((name, i) => {
            const btn = document.createElement('button');
            btn.className    = 'mc-choice-btn';
            btn.innerHTML    = '<span class="mc-key">' + LABELS[i] + '</span> ' + name;
            btn.dataset.name = name;
            btn.addEventListener('click', () => pickAnswer(name, correctName, btn));
            choicesEl.appendChild(btn);
        });
    }

    function pickAnswer(chosen, correctName, btn) {
        if (answered) return;
        answered = true;
        choicesEl.querySelectorAll('.mc-choice-btn').forEach(b => {
            if (b.dataset.name === correctName) b.classList.add('mc-choice-correct');
            else if (b === btn)                 b.classList.add('mc-choice-wrong');
            b.disabled = true;
        });
        handleResult(normalize(chosen) === normalize(correctName), correctName, chosen);
    }

    document.addEventListener('keydown', e => {
        if (answered) return;
        const idx = ['a','b','c'].indexOf(e.key.toLowerCase());
        if (idx === -1) return;
        const btn = choicesEl?.querySelectorAll('.mc-choice-btn')[idx];
        if (btn && !btn.disabled) btn.click();
    });

    // ── Výsledek ──────────────────────────────────────────────────────────
    function handleResult(isOk, correctName, typed) {
        if (activeLayer) setLayerStyle(activeLayer, isOk ? 'correct' : 'wrong');
        if (isOk) {
            correct++;
            feedbackEl.textContent = '✔ ' + correctName;
            feedbackEl.className   = 'math-feedback feedback-ok';
        } else {
            wrong++;
            mistakes.push({ name: correctName, typed });
            feedbackEl.textContent = '✘ Správně: ' + correctName;
            feedbackEl.className   = 'math-feedback feedback-err';
        }
        document.getElementById('mapScore').textContent  = correct;
        document.getElementById('mapErrors').textContent = wrong;
        current++;
        setTimeout(showNext, isOk ? 550 : 1400);
    }

    // ── Konec ─────────────────────────────────────────────────────────────
    function finishGame() {
        clearInterval(timerInt);
        choicesEl.style.display = 'none';
        const elapsed  = (Date.now() - startTime) / 1000;
        const total    = correct + wrong;
        const accuracy = total > 0 ? Math.round(correct / total * 100) : 100;

        document.getElementById('mapResFinalScore').textContent    = correct + '/' + total;
        document.getElementById('mapResFinalErrors').textContent   = wrong;
        document.getElementById('mapResFinalAccuracy').textContent = accuracy + '%';
        document.getElementById('mapResFinalTime').textContent     = Math.round(elapsed) + 's';

        const mistakesEl = document.getElementById('mapMistakes');
        if (mistakes.length) {
            mistakesEl.innerHTML = '<div class="section-title" style="margin-bottom:.5rem">Co si zapamatovat:</div>';
            mistakes.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'margin:.3rem 0;font-size:.875rem';
                row.innerHTML = 'Správně: <strong style="color:var(--accent)">' + m.name + '</strong>'
                              + ' <span style="color:var(--danger);font-size:.8rem">(ty: ' + m.typed + ')</span>';
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

    initGame();
})();
