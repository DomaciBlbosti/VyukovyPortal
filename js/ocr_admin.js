// js/ocr_admin.js — nahrání vyfocených stránek a jejich přepis přes Ollamu

(function () {
    // Fotka z telefonu má klidně 4000 px na šířku. Model z toho víc nevyčte,
    // jen se zdrží — a do databáze by šly zbytečně velké obrázky.
    const MAX_SIDE = 1600;
    const QUALITY  = 0.85;

    function shrink(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                const scale = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
                const c = document.createElement('canvas');
                c.width  = Math.round(img.width  * scale);
                c.height = Math.round(img.height * scale);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                // base64 bez „data:image/jpeg;base64," prefixu — Ollama chce holá data
                resolve(c.toDataURL('image/jpeg', QUALITY).split(',')[1]);
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Obrázek nejde načíst: ' + file.name)); };
            img.src = url;
        });
    }

    const sleep = ms => new Promise(r => setTimeout(r, ms));

    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch(OCR_URL, { method: 'POST', body: fd });
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

    /** Překreslí tabulku stránek podle stavu ze serveru */
    function renderPages(pages) {
        pages.forEach(p => {
            const row = document.querySelector(`tr[data-page="${p.id}"]`);
            if (!row) return;
            const cell = row.querySelector('.page-status');
            if (cell) {
                cell.textContent = { hotovo: '✔ přepsáno', chyba: '✘ ' + p.error,
                                     bezi: '⏳ běží' }[p.status] || '· čeká';
                if (p.edited) cell.insertAdjacentHTML('beforeend', ' <span class="mistake-hint">✎</span>');
                const time = cell.nextElementSibling;
                if (time) time.textContent = p.seconds > 0 ? p.seconds + ' s'
                                           : (p.status === 'hotovo' ? '<1 s' : '–');
            }
        });
    }

    // ── Nahrávání ──
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async () => {
            const input = document.getElementById('pages');
            const out   = document.getElementById('uploadProgress');
            const files = [...input.files];
            if (!files.length) { out.textContent = 'Nejdřív vyber nějaké stránky.'; return; }

            uploadBtn.disabled = true;
            let jobId = 0;

            try {
                for (let i = 0; i < files.length; i++) {
                    out.textContent = `Nahrávám ${i + 1} z ${files.length}…`;
                    const image = await shrink(files[i]);
                    const res = await post({
                        ajax: 'upload',
                        job_id: jobId,
                        title: document.getElementById('job_title').value,
                        note:  document.getElementById('job_note').value,
                        provider: document.getElementById('job_provider').value,
                        filename: files[i].name,
                        image,
                    });
                    if (!res.ok) throw new Error(res.error || 'Nahrání selhalo.');
                    jobId = res.job_id;
                }
                // Na stránce dávky se rovnou spustí přepis
                window.location = OCR_URL + '?job=' + jobId + '&start=1';
            } catch (e) {
                out.textContent = '✘ ' + e.message;
                uploadBtn.disabled = false;
            }
        });
    }

    // ── Přepis stránek ──
    const processBtn = document.getElementById('processBtn');
    if (processBtn) {
        const out = document.getElementById('processProgress');

        // Přepis běží na serveru nezávisle na tomhle spojení — proxy ho totiž
        // po minutě utne. Práci proto jen odpálíme a dál se ptáme na stav.
        async function runAll() {
            processBtn.disabled = true;
            let guard = 600;   // pojistka proti nekonečné smyčce

            while (guard-- > 0) {
                let st;
                try {
                    st = await post({ ajax: 'status', job_id: OCR_JOB_ID });
                } catch (e) {
                    out.textContent = '✘ Nepodařilo se zjistit stav: ' + e.message;
                    processBtn.disabled = false;
                    return;
                }
                renderPages(st.pages);
                if (!st.remaining) break;

                const target = st.next_id;
                const done   = st.pages.filter(p => p.status === 'hotovo' || p.status === 'chyba').length;
                out.textContent = `Přepisuji stránku ${done + 1} z ${st.pages.length}… `
                                + '(u velkého modelu to může být i pár minut)';

                // Odpálíme zpracování a na odpověď nespoléháme; když spojení
                // padne, práce doběhne a my ji uvidíme v dalším dotazu na stav
                post({ ajax: 'process', job_id: OCR_JOB_ID }).catch(() => {});

                while (guard-- > 0) {
                    await sleep(4000);
                    let cur;
                    try { cur = await post({ ajax: 'status', job_id: OCR_JOB_ID }); }
                    catch (e) { continue; }   // výpadek dotazu ještě neznamená konec práce
                    renderPages(cur.pages);
                    const page = cur.pages.find(p => p.id === target);
                    if (!page || page.status === 'hotovo' || page.status === 'chyba') break;
                }
            }

            out.textContent = '✔ Hotovo. Načítám přepis…';
            window.location = OCR_URL + '?job=' + OCR_JOB_ID;
        }

        processBtn.addEventListener('click', runAll);
        // Po nahrání se přepis rozjede sám, ať se nemusí klikat podruhé
        if (new URLSearchParams(location.search).get('start') && !processBtn.disabled) runAll();
    }

    // ── Sestavení sady ──
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
                job_id: OCR_JOB_ID,
                text: document.getElementById('ocrText').value,
                set_title: document.getElementById('set_title').value,
                source: document.getElementById('source').value,
                subject: document.getElementById('subject').value,
                kind: document.getElementById('kind').value,
                grade: document.getElementById('grade').value,
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
                try { st = await post({ ajax: 'build_status', job_id: OCR_JOB_ID }); }
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
