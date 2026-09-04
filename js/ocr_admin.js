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

    function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        return fetch(OCR_URL, { method: 'POST', body: fd }).then(r => r.json());
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

        async function runAll() {
            processBtn.disabled = true;
            let guard = 200;   // pojistka proti nekonečné smyčce, kdyby server vracel nesmysly

            while (guard-- > 0) {
                out.textContent = 'Přepisuji… (může to trvat minuty na stránku)';
                let res;
                try {
                    res = await post({ ajax: 'process', job_id: OCR_JOB_ID });
                } catch (e) {
                    out.textContent = '✘ Spojení se serverem selhalo: ' + e.message;
                    processBtn.disabled = false;
                    return;
                }

                if (res.done) break;

                const row = document.querySelector(`tr[data-page="${res.page.id}"] .page-status`);
                if (row) {
                    row.textContent = res.page.status === 'hotovo'
                        ? '✔ přepsáno'
                        : '✘ ' + res.page.error;
                    const timeCell = row.nextElementSibling;
                    if (timeCell) timeCell.textContent = res.page.seconds + ' s';
                }
                out.textContent = `Hotová stránka ${res.page.position}, zbývá ${res.remaining}…`;
                if (!res.remaining) break;
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
        buildBtn.addEventListener('click', async () => {
            const out = document.getElementById('buildProgress');
            buildBtn.disabled = true;
            out.textContent = 'Skládám sadu…';

            try {
                const res = await post({
                    ajax: 'build',
                    job_id: OCR_JOB_ID,
                    text: document.getElementById('ocrText').value,
                    set_title: document.getElementById('set_title').value,
                    source: document.getElementById('source').value,
                    subject: document.getElementById('subject').value,
                    kind: document.getElementById('kind').value,
                    grade: document.getElementById('grade').value,
                });
                if (!res.ok) throw new Error(res.error || 'Sestavení selhalo.');

                document.getElementById('buildResult').style.display = 'block';
                document.getElementById('buildJson').value  = res.json;
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
                    errBox.textContent = `✔ Sada vypadá v pořádku — ${res.count} položek.`;
                }
                out.textContent = '';
            } catch (e) {
                out.textContent = '✘ ' + e.message;
            } finally {
                buildBtn.disabled = false;
            }
        });

        // Ruční úprava JSONu se musí propsat do předávacího formuláře
        document.getElementById('buildJson').addEventListener('input', e => {
            document.getElementById('handoffJson').value = e.target.value;
        });
    }
})();
