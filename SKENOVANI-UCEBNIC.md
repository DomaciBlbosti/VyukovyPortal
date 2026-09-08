# Skenování učebnic přes Ollamu

Vyfotíš stránky učebnice telefonem, nahraješ je do adminu a model z nich
udělá sadu na procvičování.

Číst je může buď **Ollama u tebe doma** (nic neodchází), nebo **komerční API**
(přesnější přepis, ale fotky opustí domácí síť). Vybírá se v nastavení a dá se
změnit u každého spuštění.

## Čtyři záložky

Admin → 🔍 Skenování učebnic má čtyři části, každou na vlastní stránce:

| Záložka | Co se tam dělá |
|---|---|
| 🖼️ **Galerie** | alba fotek: nahrát, přesunout mezi alby, přeřadit, smazat |
| 🔍 **Přepis (OCR)** | vybrat fotky z alba, model a zadání, pustit; u každé fotky historie běhů |
| 🧩 **Tvorba sad** | z přepsaného textu složit JSON sady a předat ho do importu |
| ⚙️ **Modely a zadání** | Ollama, komerční API, velikost kontextu, výchozí zadání a sada zadání k vyzkoušení |

Fotka se nahrává jednou a přepisovat se dá kolikrát chceš — každé spuštění
je **běh** a stránka si je pamatuje všechny. Stejný obrázek tak jde zkusit
třemi modely a čtyřmi zadáními a pak si vybrat, který přepis platí.

## Jak to funguje

Postup má tři kroky a mezi nimi tebe:

1. **Přepis** — vision model přečte každou stránku zvlášť a vrátí text.
2. **Kontrola** — přepis si přečteš proti originálu a opravíš, co model zkomolil.
3. **Sestavení** — textový model z opraveného přepisu složí JSON sady.

Ten prostřední krok tam není omylem. Malé modely mají s češtinou problém,
hlavně s háčky a čárkami. Když opravíš přepis, oprava se propíše do sady;
kdyby se přepis přeskočil, chyba by dojela až k dítěti.

Výsledný JSON se navíc **neuloží rovnou** — jde do stejného validátoru jako
ručně vložená sada. Když model vyrobí duplicitu nebo zapomene odpověď,
řekne ti to a do databáze se nedostane nic nezkontrolovaného.

## Galerie

Založ album (typicky jedna lekce), nahraj do něj fotky. Prohlížeč je před
odesláním zmenší (delší strana 1600 px) a udělá náhled, takže fotky z telefonu
vadit nebudou. Fotky jdou mezi alby přesouvat, řadit šipkami a mazat; smazání
alba smaže i fotky a všechny jejich přepisy. Sady, které z alba vznikly, to
nijak neovlivní — jsou uložené zvlášť.

Fotky se drží, dokud je nesmažeš. U každého alba je vidět, kolik místa
v databázi zabírá.

## Přepis

Otevři album, zaškrtni stránky (předvybrané jsou ty bez přepisu), vyber model
a zadání a dej *Přepsat vybrané*. Přepis se rozjede stránku po stránce a u každé
vidíš stav i čas. **Práce běží na serveru nezávisle na prohlížeči** — když
stránku zavřeš nebo spojení utne reverzní proxy, přepis doběhne a po návratu ho
tam najdeš hotový.

### Detail stránky a historie běhů

*Detail* u stránky otevře originál vedle přepisu v editovatelném poli — dá se
číst proti sobě, ne hádat. Na mobilu je fotka nad textem, na širokém displeji
vedle sebe.

Pod tím je **Spustit model znovu na tuhle stránku** s jiným modelem nebo
zadáním a **historie běhů**: kdy, jaký model, jaké zadání, kolik znaků a
sekund. Nejnovější úspěšný běh se stává platným přepisem sám; kterýkoli starší
vybereš tlačítkem *Použít*. Text každého běhu si rozbalíš tlačítkem *Text*.

Ruční oprava má vždycky přednost: když stránku opravíš a pak pustíš další
běh, oprava zůstane platná, dokud si sám nevybereš jiný běh. Přijít o ni
omylem nejde.

### Bloky, cvičení a obrázky

Se zadáním *DeepSeek-OCR — markdown* (`<|grounding|>Convert the document to
markdown.`) vrací model ke každému kusu stránky i rámeček: nadpis, odstavec,
obrázek. Aplikace z toho udělá **bloky** a seskupí je do **cvičení** — nová
skupina začíná nadpisem nebo číslem cvičení (*1*, *2* … tak, jak je má
pracovní sešit). V detailu stránky je vidíš pod přepisem; v *Tvorbě sad* si
pak zaškrtneš jen to cvičení, ze kterého má sada vzniknout, ne celou stránku.

**Obrázky na stránce** se podle rámečku vyříznou z fotky a uloží k bloku.
Vyřezává je server (potřebuje rozšíření GD, které je v obrazu kontejneru);
kdyby chybělo, výřez si udělá prohlížeč jen pro zobrazení. V přepisu je
místo obrázku značka `[obrázek 1]`, aby model při skládání sady věděl, že
tam něco bylo, ale nezkoušel to popisovat.

Zadání *Free OCR.* rámečky nedává — je nejrychlejší a text má stejně
dobrý, ale stránka je pak jeden blok a obrázky se neuloží. Pro stránky
s cvičeními proto ber markdown.

Co model neumí: prázdné řádky na doplnění (`________`) v přepisu nejsou.
U doplňovaček z pracovního sešitu je tedy třeba text zkontrolovat a mezery
dopsat jako `_`, jinak si je model při skládání sady domyslí.

### Varování u běhu

Aplikace hlídá dva typické průšvihy, které by jinak prošly jako „hotovo":

- **zacyklení** — model vrátí stovky řádků, ale jen pár různých,
- **opakování zadání** — model místo stránky přepsal instrukci, kterou dostal.

Oba se ukážou jako ⚠ u běhu. Takový přepis nepoužívej, zkus jiné zadání
(viz níž) nebo model.

## Zadání pro model (prompty)

Tohle je nejdůležitější nastavení a záleží na typu modelu.

**Specializované OCR modely** — `deepseek-ocr` a co je na něm postavené
(`HSR-DeepThink/strike-ocr` apod.) — jsou naučené na několik krátkých
anglických vět. Na dlouhé české zadání reagují tím, že ho opakují dokola.
Pro ně:

| Zadání | Přesné znění | Kdy |
|---|---|---|
| Free OCR. | `Free OCR.` | holý text, nejrychlejší — **začni tímhle** |
| markdown | `<\|grounding\|>Convert the document to markdown.` | nadpisy, tabulky, seznamy |
| OCR this image | `<\|grounding\|>OCR this image.` | text s rozvržením |
| Extract the text | `Extract the text in the image.` | když Free OCR vrací málo |
| Strike-OCR | `Extract text from this image` | znění od autora strike-ocr, bez tečky |

**Obecné vision modely** — `gemma`, `qwen2.5vl`, `minicpm-v`,
`llama3.2-vision`, `gpt-4o-mini` — naopak delší zadání potřebují, jinak
stránku shrnou nebo přeloží. Pro ně jsou presety *Obecný vision model —
anglicky* a *— česky*.

Model je na přesné znění citlivý, i tečka na konci hraje roli, proto se
zadání posílá doslova. Obrázek se posílá zvlášť (Ollama si ho do zadání vloží
sama — značka `<image>` z dokumentace DeepSeeku se do zadání nepíše).
Souřadnicové značky `<|ref|>…<|/ref|><|det|>…<|/det|>`, které model
s `<|grounding|>` přidává, aplikace z výstupu odstraní.

### Jak zadání otestovat

1. V galerii otevři jednu typickou stránku (slovíčka, nebo souvislý text).
2. V *Přepisu* u ní dej *Spustit znovu* postupně s každým zadáním.
3. V historii běhů porovnej délku, čas, varování a rozbalený text.
4. Co vyhrálo, nastav jako výchozí v *Modely a zadání*.

Výchozí zadání i model se dají u každého spuštění změnit.

## Tvorba sad

Vyber album, zaškrtni stránky a dej *Načíst text ze stránek*. Text můžeš
ještě upravit (uloží se k albu). Vyplň název, zdroj, předmět, typ a ročník,
vyber, kdo skládá (Ollama, nebo API), a dej *Sestavit JSON*. Aplikace ukáže
výsledek i s případnými chybami; JSON si můžeš rovnou upravit a pak ho pošleš
přes *Otevřít v importu sad* do finální kontroly a uložení.

Typ sady vybírej podle toho, co je na stránkách:

| Typ | Kdy |
|---|---|
| dvojice | dvousloupcový seznam slovíček, veličin, letopočtů |
| vyber | otázky s nabídnutými odpověďmi |
| doplnovacka | věty s vynechaným slovem |
| cteni | souvislý text a otázky k němu |

**Na skládání sady patří obecný model** (gemma4, qwen3), ne specializovaný
OCR model — ten JSON nesloží. Když je v nastavení jako textový model něco
s „ocr" v názvu, aplikace na to upozorní červeně.

## Ollama, nebo komerční API?

|  | Ollama | Komerční API |
|---|---|---|
| Fotky učebnice | zůstanou doma | odejdou ven |
| Přesnost přepisu | slabší, hlavně diakritika | výrazně lepší |
| Rychlost | podle karty, klidně minuty/stránku | sekundy |
| Cena | proud | řádově haléře za stránku |
| Kontext | musíš hlídat (viz níž) | v praxi neomezený |

Nemusíš volit jednou provždy. Rozumné je jet na Ollamě a sáhnout po API u
stránky, kterou lokální model nezvládl — v detailu stránky dáš *Spustit
znovu* a v seznamu modelů vybereš ten z API.

**Rozhraní OpenAI umí i OpenRouter, Groq a další** — stačí přepsat adresu,
žádná další úprava kódu není potřeba.

### API klíč

Vyplníš ho v nastavení; ukládá se do databáze a **do stránky se nikdy nevypisuje
celý**, jen zamaskovaně (`sk-…a1b2`). Prázdné pole při ukládání znamená „nech
ho být", ne „smaž ho" — na smazání je zvlášť zaškrtávátko.

## Co potřebuješ pro Ollamu

### Ollama vedle aplikace

Na TrueNASu ji nainstaluj jako samostatnou aplikaci z katalogu. Aplikace se
na ni pak dostane přes jméno kontejneru, typicky `http://ollama:11434`.
Když ji máš na jiném stroji v síti, použij jeho IP: `http://192.168.1.10:11434`.

Ollama musí být ze sítě dostupná — ve výchozím nastavení poslouchá jen na
`127.0.0.1`. V proměnných prostředí nastav:

```
OLLAMA_HOST=0.0.0.0
```

### Dva modely

```bash
ollama pull deepseek-ocr        # čtení obrázků, ~6.7 GB (potřebuje Ollamu 0.13+)
ollama pull qwen2.5             # sestavení sady, ~5 GB
```

Na čtení jde místo `deepseek-ocr` použít i obecný vision model
(`gemma3:12b`, `qwen2.5vl`, `minicpm-v`) — jen mu dej jiné zadání, viz výš.

**Když máš málo paměti na kartě, dej do obou polí tentýž obecný model.** Dva
různé se na 12 GB nevejdou současně a Ollama by je mezi krokem „přepis" a
„sestavení" pořád přenačítala. Gemma 3 od velikosti 4B nahoru umí obrázky
i text, takže pokryje obojí sama. K tomu se hodí `OLLAMA_KEEP_ALIVE=30m`,
jinak Ollama model po pěti minutách nečinnosti uvolní.

Aplikace se před přepisem u Ollamy zeptá, jestli model umí obrázky
(`/api/show`), a textovému modelu fotku vůbec nepošle — řekne to rovnou.

**Uvažovací modely se na přepis nehodí.** Myšlenkový postup jim spolyká celý
kontext a k samotnému přepisu se nedostanou — vypadá to jako by model po pár
minutách vrátil prázdno. Aplikace uvažování vypíná (`think: false`), ale ne
každý model to respektuje; když to na takový narazíš, řekne ti to a poradí
sáhnout po jiném.

Na čtení obrázků jde použít i `minicpm-v` nebo `qwen2.5vl`. **Počítej s tím,
že tohle je slabé místo celého řetězu** — malé vision modely dělají v české
diakritice chyby a rozvržení stránky (sloupce, tabulky, číslování cvičení)
jim dělá potíže. Proto ta ruční kontrola mezi kroky.

Bez grafické karty to poběží, ale pomalu — klidně minuty na stránku. S GPU
jsou to jednotky sekund.

## Nastavení v aplikaci

**Admin → 🔍 Skenování učebnic → ⚙️ Modely a zadání** — vyber výchozího
poskytovatele a vyplň, co k němu patří. Jakmile spojení funguje, aplikace si
sama načte seznam dostupných modelů a nabídne ho v rozbalovacím seznamu.

### Velikost kontextu (jen Ollama)

Ollama má ve výchozím stavu jen pár tisíc tokenů kontextu a **co se nevejde,
tiše zahodí** — u sady sestavené z několika stránek by pak potichu chyběla
poslední slovíčka. Aplikace si proto kontext říká sama; nastavuje se ve stejném
formuláři jako modely a výchozí hodnota je 8192.

Větší kontext zabere víc paměti na kartě. Na 12 GB je 8192 rozumný začátek;
když máš dávky delší, zvyš ho a sleduj, jestli se model ještě vejde do VRAM.

Pod textem v *Tvorbě sad* je vždycky vidět odhad, kolik tokenů zabírá a
kolik je nastaveno — a když se to nemá šanci vejít, aplikace to řekne dřív,
než dáš *Sestavit JSON*.

## Poznámka k autorským právům

Pro vlastní potřebu (§30 autorského zákona) si můžeš učebnici oskenovat a
procvičovat podle ní doma s vlastními dětmi. Pole *zdroj* je u každé sady
povinné právě proto, aby bylo vidět, odkud obsah je.

Celý portál je za přihlášením a to je to podstatné — obsah z učebnic se
nikam veřejně nedostane. Nedělej z něj veřejné demo ani režim pro hosty.
