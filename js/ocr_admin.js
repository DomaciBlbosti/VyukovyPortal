// js/ocr_admin.js — galerie, přepis a tvorba sad (jeden soubor, části se
// zapínají podle toho, které prvky na stránce jsou)

(function () {
    // Fotka z telefonu má klidně 4000 px na šířku. Model z toho víc nevyčte,
    // jen se zdrží — a do databáze by šly zbytečně velké obrázky.
    const MAX_SIDE   = 1600;
    const THUMB_SIDE = 240;
    const QUALITY    = 0.85;

    /** Načte soubor jednou a vrátí plný i náhledový JPEG jako holé base64 */
    function shrink(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                const draw = side => {
                    const scale = Math.min(1, side / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width  = Math.round(img.width  * scale);
                    c.height = Math.round(img.height * scale);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    // bez „data:image/jpeg;base64," prefixu — Ollama chce holá data
                    return c.toDataURL('image/jpeg', QUALITY).split(',')[1];
                };
                resolve({ image: draw(MAX_SIDE), thumb: draw(THUMB_SIDE) });
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Obrázek nejde načíst: ' + file.name)); };
            img.src = url;
        });
    }

    const sleep = ms => new Promise(r => setTimeout(r, ms));

    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch(OCR_AJAX_URL, { method: 'POST', body: fd });
        const body = await res.text();
        try {
            return JSON.parse(body);
        } catch (e) {
            // Když před aplikací stojí proxy, vrátí při vypršení limitu HTML
            // chybovou stránku. Ať je z hlášky poznat, o co jde.
            throw new Error(body.trim().startsWith('<')
                ? `server vrátil HTML místo odpovědi (HTTP ${res.status}) — nejspíš vypršel časový limit proxy`
                : `odpověď serveru nešla přečíst (HTTP ${res.status})`);
        }
    }

    // ── Výběr zadání: při změně presetu se přepíše text ──
    const promptKey  = document.getElementById('prompt_key');
    const promptText = document.getElementById('prompt_text');
    if (promptKey && promptText && typeof OCR_PROMPTS !== 'undefined') {
        promptKey.addEventListener('change', () => {
            const p = OCR_PROMPTS[promptKey.value];
            if (!p) return;
            if (promptKey.value !== 'custom' || p.prompt) promptText.value = p.prompt;
            const note = document.getElementById('prompt_note');
            if (note) note.textContent = p.note || '';
            if (promptKey.value === 'custom') promptText.focus();
        });
    }

    // ── Zaškrtnout vše ──
    const pickAll = document.getElementById('pickAll');
    if (pickAll) pickAll.addEventListener('change', () => {
        document.querySelectorAll('.page-pick').forEach(c => { c.checked = pickAll.checked; });
    });
    // hodnoty jsou ID stránek (přepis) nebo klíče cvičení „stránka:index" (tvorba)
    const picked = () => [...document.querySelectorAll('.page-pick:checked')].map(c => /^\d+$/.test(c.value) ? parseInt(c.value, 10) : c.value);

    // ── Galerie: nahrávání ──
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async () => {
            const input = document.getElementById('pages');
            const out   = document.getElementById('uploadProgress');
            const files = [...input.files];
            if (!files.length) { out.textContent = 'Nejdřív vyber nějaké fotky.'; return; }

            uploadBtn.disabled = true;
            const albumId = parseInt(uploadBtn.dataset.album, 10) || 0;
            try {
                for (let i = 0; i < files.length; i++) {
                    out.textContent = `Nahrávám ${i + 1} z ${files.length}…`;
                    const { image, thumb } = await shrink(files[i]);
                    const res = await post({ ajax: 'upload', album_id: albumId, filename: files[i].name, image, thumb });
                    if (!res.ok) throw new Error(res.error || 'Nahrání selhalo.');
                }
                window.location = OCR_AJAX_URL + '?album=' + albumId;
            } catch (e) {
                out.textContent = '✘ ' + e.message;
                uploadBtn.disabled = false;
            }
        });
    }

    // ── Přepis: fronta běhů a sledování postupu ──
    function renderRuns(runs) {
        runs.forEach(r => {
            const row = document.querySelector(`tr[data-page="${r.page_id}"]`);
            if (!row) return;
            const cell = row.querySelector('.page-status');
            if (!cell) return;
            cell.textContent = { hotovo: '✔ přepsáno', chyba: '✘ ' + r.error, bezi: '⏳ běží' }[r.status] || '· ve frontě';
            if (r.warning) cell.insertAdjacentHTML('beforeend', ` <span style="color:var(--danger);font-size:.8rem">⚠ ${r.warning.replace(/</g, '&lt;')}</span>`);
            const time = row.querySelector('.page-time');
            if (time) time.textContent = r.seconds > 0 ? r.seconds + ' s' : (r.status === 'hotovo' ? '<1 s' : '–');
        });
    }

    /**
     * Rozjede zpracování dávky a hlídá ho až do konce.
     *
     * Přepis běží na serveru nezávisle na tomhle spojení — proxy ho totiž po
     * minutě utne. Práci proto jen odpálíme a dál se ptáme na stav.
     */
    async function runBatch(batch, out, onDone) {
        let guard = 600;   // pojistka proti nekonečné smyčce
        while (guard-- > 0) {
            let st;
            try {
                st = await post({ ajax: 'status', batch });
            } catch (e) {
                out.textContent = '✘ Nepodařilo se zjistit stav: ' + e.message;
                return false;
            }
            renderRuns(st.runs);
            if (!st.remaining) break;

            const target = st.next_id;
            const done   = st.runs.length - st.remaining;
            out.textContent = `Přepisuji ${done + 1} z ${st.runs.length}… (u velkého modelu to může být i pár minut)`;

            // Odpálíme zpracování a na odpověď nespoléháme; když spojení
            // padne, práce doběhne a my ji uvidíme v dalším dotazu na stav
            post({ ajax: 'process', batch }).catch(() => {});

            while (guard-- > 0) {
                await sleep(4000);
                let cur;
                try { cur = await post({ ajax: 'status', batch }); }
                catch (e) { continue; }   // výpadek dotazu ještě neznamená konec práce
                renderRuns(cur.runs);
                const run = cur.runs.find(r => r.id === target);
                if (!run || run.status === 'hotovo' || run.status === 'chyba') break;
            }
        }
        out.textContent = '✔ Hotovo.';
        onDone();
        return true;
    }

    async function queueAndRun(pages, btn, out, onDone) {
        btn.disabled = true;
        out.textContent = 'Zařazuji do fronty…';
        try {
            const res = await post({
                ajax: 'queue',
                pages: JSON.stringify(pages),
                model_pick:  document.getElementById('model_pick').value,
                prompt_key:  document.getElementById('prompt_key').value,
                prompt_text: document.getElementById('prompt_text').value,
            });
            if (!res.ok) throw new Error(res.error || 'Zařazení selhalo.');
            if (!res.runs.length) throw new Error('Nic k přepsání — vybrané stránky už ve frontě jsou.');
            if (!(await runBatch(res.batch, out, onDone))) btn.disabled = false;
        } catch (e) {
            out.textContent = '✘ ' + e.message;
            btn.disabled = false;
        }
    }

    const runBtn = document.getElementById('runBtn');
    if (runBtn) {
        runBtn.addEventListener('click', () => {
            const out = document.getElementById('runProgress');
            const ids = picked();
            if (!ids.length) { out.textContent = 'Nejdřív zaškrtni nějaké stránky.'; return; }
            queueAndRun(ids, runBtn, out, () => window.location.reload());
        });
    }

    const rerunBtn = document.getElementById('rerunBtn');
    if (rerunBtn) {
        rerunBtn.addEventListener('click', () => {
            const out = document.getElementById('runProgress');
            queueAndRun([parseInt(rerunBtn.dataset.page, 10)], rerunBtn, out, () => window.location.reload());
        });
    }

    // Bloky bez uloženého výřezu (server bez GD): vyřízne je prohlížeč z fotky
    const cropCanvases = document.querySelectorAll('canvas.ocr-crop[data-box]');
    if (cropCanvases.length && typeof PAGE_IMAGE_URL === 'string') {
        const img = new Image();
        img.onload = () => cropCanvases.forEach(c => {
            const [x1, y1, x2, y2] = c.dataset.box.split(',').map(Number);
            const pad = 8;
            const sx = Math.max(0, Math.floor(x1 / 1000 * img.width) - pad);
            const sy = Math.max(0, Math.floor(y1 / 1000 * img.height) - pad);
            const sw = Math.min(img.width, Math.ceil(x2 / 1000 * img.width) + pad) - sx;
            const sh = Math.min(img.height, Math.ceil(y2 / 1000 * img.height) + pad) - sy;
            if (sw < 8 || sh < 8) return;
            c.width = sw; c.height = sh;
            c.getContext('2d').drawImage(img, sx, sy, sw, sh, 0, 0, sw, sh);
        });
        img.src = PAGE_IMAGE_URL;
    }

    // Historie běhů: rozbalit text
    document.querySelectorAll('.run-toggle').forEach(b => b.addEventListener('click', () => {
        const row = document.getElementById('runText' + b.dataset.run);
        if (row) row.hidden = !row.hidden;
    }));

    // ── Tvorba: text ze stránek a sestavení sady ──
    const loadTextBtn = document.getElementById('loadTextBtn');
    const ocrText     = document.getElementById('ocrText');
    if (loadTextBtn && ocrText && typeof PAGE_TEXTS !== 'undefined') {
        const estimate = () => {
            const est  = Math.ceil(ocrText.value.length / 3);
            const el   = document.getElementById('tokenEstimate');
            const warn = document.getElementById('tokenWarn');
            if (el)   el.textContent = est;
            if (warn) warn.hidden = !(est * 2 + 500 > OCR_CTX);
        };
        loadTextBtn.addEventListener('click', () => {
            ocrText.value = picked().map(id => PAGE_TEXTS[id] || '').filter(Boolean).join('\n\n');
            estimate();
        });
        ocrText.addEventListener('input', estimate);
    }

    const buildBtn = document.getElementById('buildBtn');
    if (buildBtn) {
        // Skládání sady je stejně dlouhé jako přepis, takže i tady jen
        // odpálíme práci a výsledek si vyzvedneme dotazem na stav.
        buildBtn.addEventListener('click', async () => {
            const out     = document.getElementById('buildProgress');
            const warnBox = document.getElementById('buildWarning');
            buildBtn.disabled = true;
            out.textContent = 'Skládám sadu…';
            warnBox.style.display = 'none';

            const params = {
                ajax: 'build',
                album_id: OCR_ALBUM_ID,
                text: document.getElementById('ocrText').value,
                set_title: document.getElementById('set_title').value,
                source: document.getElementById('source').value,
                subject: document.getElementById('subject').value,
                kind: document.getElementById('kind').value,
                grade: document.getElementById('grade').value,
                provider: document.getElementById('build_provider').value,
            };

            // Odpověď použijeme, když dorazí — nese navíc varování o kontextu
            // a rovnou zkontrolovanou sadu. Když spojení padne, dojdeme si
            // pro výsledek na server.
            let res = null;
            const direct = post(params).then(r => { res = r; }).catch(() => {});

            let guard = 300;
            while (guard-- > 0) {
                await Promise.race([direct, sleep(4000)]);
                if (res) break;
                let st;
                try { st = await post({ ajax: 'build_status', album_id: OCR_ALBUM_ID }); }
                catch (e) { continue; }
                if (!st.building) {
                    res = st.json
                        ? { ok: true, json: st.json, errors: [], count: null }
                        : { ok: false, error: st.error || 'Sestavení skončilo bez výsledku.' };
                    break;
                }
            }
            if (!res) res = { ok: false, error: 'Sestavení trvá neobvykle dlouho — zkus to znovu.' };

            buildBtn.disabled = false;

            // Varování o krátkém kontextu platí i když volání selže —
            // často je právě ono tím důvodem
            warnBox.style.display = res.warning ? 'block' : 'none';
            warnBox.textContent   = res.warning || '';

            if (!res.ok) { out.textContent = '✘ ' + (res.error || 'Sestavení selhalo.'); return; }

            document.getElementById('buildResult').style.display = 'block';
            document.getElementById('buildJson').value   = res.json;
            document.getElementById('handoffJson').value = res.json;

            const errBox = document.getElementById('buildErrors');
            if (res.errors && res.errors.length) {
                errBox.className = 'alert alert-error';
                errBox.innerHTML = '<strong>Model to nesložil čistě — oprav to v JSONu níž:</strong>';
                const ul = document.createElement('ul');
                ul.style.margin = '.25rem 0 0 1.1rem';
                res.errors.slice(0, 10).forEach(e => {
                    const li = document.createElement('li');
                    li.textContent = e;
                    ul.appendChild(li);
                });
                errBox.appendChild(ul);
            } else {
                errBox.className = 'alert alert-success';
                errBox.textContent = res.count === null
                    ? '✔ Sada složena — projdi ji níž.'
                    : `✔ Sada vypadá v pořádku — ${res.count} položek.`;
            }
            out.textContent = '';
        });

        // Ruční úprava JSONu se musí propsat do předávacího formuláře
        document.getElementById('buildJson').addEventListener('input', e => {
            document.getElementById('handoffJson').value = e.target.value;
        });
    }
})();
