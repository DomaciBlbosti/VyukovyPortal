# Skenování učebnic přes Ollamu

Vyfotíš stránky učebnice telefonem, nahraješ je do adminu a model z nich
udělá sadu na procvičování.

Všechno jde přes **Ollama Proxy** — jednu adresu, jeden klíč. Jestli stránku
přečte model na tvé kartě, nebo komerční API, rozhoduje jen jeho **název**;
klíče k poskytovatelům drží proxy, aplikace je nikdy nevidí.

## Čtyři záložky

Admin → 🔍 Skenování učebnic má čtyři části, každou na vlastní stránce:

| Záložka | Co se tam dělá |
|---|---|
| 🖼️ **Galerie** | podklady po předmětech a ročnících: nahrát, přesunout mezi alby, přeřadit, smazat |
| 🔍 **Přepis (OCR)** | vybrat fotky z alba, model a zadání, pustit; u každé fotky historie běhů |
| 🧩 **Tvorba sad** | z přepsaného textu složit JSON sady a předat ho do importu |
| ⚙️ **Modely a zadání** | adresa a klíč proxy, modely, velikost kontextu, výchozí zadání a sada zadání k vyzkoušení |

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

Album je **podklad k jednomu předmětu a ročníku**: „🇬🇧 Angličtina · 6. třída
— Project 1, pracovní sešit". Učebnice a pracovní sešit dej jako dvě alba
téhož předmětu; předmětů i ročníků může mít album kolik chceš vedle sebe
a v seznamu se seskupí pod společný nadpis.

Proč to stojí za vyplnění: sady z alba předmět a ročník **zdědí**, takže je
už nepíšeš znovu, a u každé sady je odkaz zpátky na podklad — je vidět,
z čeho otázky čerpají. V albu naopak vidíš seznam sad, které z něj vznikly.
Nezařazená alba se ukážou dole pod „📂 Bez předmětu"; zařadit je jde kdykoli
později v hlavičce alba.

Založ album, nahraj do něj fotky. Prohlížeč je před
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
dobrý, ale stránka je pak jeden blok a obrázky se neuloží. Zato jako jediné
**zachová vynechávky** (`______`), které pracovní sešit má na doplnění;
zadání s rámečky je zahazují.

**Pro pracovní sešity proto ber *DeepSeek-OCR — rámečky + vynechávky*.**
Jsou to dvě volání na stejnou fotku: první (markdown s rámečky) dá rozvržení
a obrázky, druhé (*Free OCR.*) text s vynechávkami, a aplikace ke každému
řádku bloku dohledá stejný řádek z druhého přepisu a vezme jeho znění.
Výsledek má bloky, obrázky i `______`. Trvá dvakrát déle a když se texty
obou volání moc neshodují, běh to řekne varováním.

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
| Free OCR. | `Free OCR.` | holý text, nejrychlejší, zachová vynechávky — **začni tímhle** |
| rámečky + vynechávky | markdown a pak `Free OCR.` (2 volání) | pracovní sešity: bloky, obrázky i `______` |
| markdown | `<\|grounding\|>Convert the document to markdown.` | nadpisy, tabulky, seznamy, obrázky; vynechávky zahodí |
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

### Co vyšlo z měření

Zkoušeno na dvaceti stránkách — učebnice fyziky pro 6. třídu a pracovní sešit
Project 1 — na kartě s 12 GB.

| Model | Zadání | Čas na stránku | Jak dopadl |
|---|---|---|---|
| `HSR-DeepThink/strike-ocr` | Free OCR. | 3–5 s | přesná čeština i s diakritikou, vynechávky sedí |
| `HSR-DeepThink/strike-ocr` | rámečky + vynechávky | 4–28 s | totéž a k tomu bloky a obrázky; na 8 z 20 stránek varování |
| `HSR-DeepThink/strike-ocr` | OCR this image | 5–8 s | prošlo i tam, kde ostatní zadání selhala; bez obrázků a bez `______` |
| `gemma4:12b` | Obecný vision model — česky | 124 s | čtyřicetkrát pomalejší a češtinu si domýšlí („roztižní výkladní auto") |
| `qwen3-vl:8b` | cokoli | 75 s a nic | celou odpověď spotřebuje na uvažování, `think: false` ignoruje |

Rozumné nastavení: **`strike-ocr` + rámečky a vynechávky**. Na stránkách,
kde běh hlásí varování, dej *Spustit znovu* se zadáním *OCR this image* —
v testu prošly všechny čtyři, na kterých kombinované zadání selhalo.

**Pozor na obrázkové nápovědy.** Na stránce, kde se do vynechávek doplňují
slova podle obrázků, si `Free OCR.` odpovědi vymyslel — do přepisu napsal
*Maths*, *English*, *Geography*, *Football*, ačkoli v sešitě jsou prázdné
řádky. Zadání *OCR this image* to nedělá. Než z takové stránky složíš sadu,
porovnej přepis s fotkou; kvůli tomu je detail stránky vedle sebe.

### Jak zadání otestovat

1. V galerii otevři jednu typickou stránku (slovíčka, nebo souvislý text).
2. V *Přepisu* u ní dej *Spustit znovu* postupně s každým zadáním.
3. V historii běhů porovnej délku, čas, varování a rozbalený text.
4. Co vyhrálo, nastav jako výchozí v *Modely a zadání*.

Výchozí zadání i model se dají u každého spuštění změnit.

## Tvorba sad

Vyber album, zaškrtni stránky a dej *Načíst text ze stránek*. Text můžeš
ještě upravit (uloží se k albu). Předmět a ročník jsou předvyplněné z alba,
takže zbývá název, zdroj a typ;
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

**Na skládání sady patří obecný model** (gemma4, qwen3, gpt-4o-mini), ne
specializovaný OCR model — ten JSON nesloží. Když je v nastavení jako textový
model něco s „ocr" v názvu, aplikace na to upozorní červeně. U jednotlivého
sestavení si můžeš vybrat jiný model, než je ten výchozí.

## Přes co to jde: Ollama Proxy

Aplikace zná jedinou adresu a na ni posílá úplně všechno — přepis stránek
i skládání sad. Prokazuje se svým klíčem:

```
Authorization: Bearer opx_…
```

Klíč vytvoříš ve správě proxy a nastavíš mu tam, které modely smí. V aplikaci
ho vyplníš v *Modely a zadání*; do stránky se nikdy nevypisuje celý, jen
zamaskovaně. Prázdné pole při ukládání znamená „nech ho být", ne „smaž ho" —
na smazání je zvlášť zaškrtávátko.

### Lokální, nebo komerční model?

Nevybíráš poskytovatele, vybíráš **model**. V seznamu jsou obojí a proxy podle
názvu pozná, kam požadavek poslat.

|  | Model u tebe doma | Komerční model |
|---|---|---|
| Fotky učebnice | zůstanou doma | odejdou ven |
| Přesnost přepisu | slabší, hlavně diakritika | výrazně lepší |
| Rychlost | podle karty, klidně minuty/stránku | sekundy |
| Cena | proud | řádově haléře za stránku |
| Kontext | musíš hlídat (viz níž) | v praxi neomezený |

Nemusíš volit jednou provždy. Rozumné je jet na lokálním modelu a u stránky,
kterou nezvládl, sáhnout v detailu po *Spustit znovu* s komerčním.

U každého běhu je v historii vidět, čím se četlo: 🏠 doma, ☁️ komerčně
i s názvem poskytovatele. Za měsíc tak poznáš, jestli ta stránka opustila
domácí síť.

Když aplikace klíč nemá, uvidí jen lokální modely. Komerční se v seznamu
objeví, až klíč vyplníš.

### Když v seznamu model chybí

Seznam se skládá ze dvou dotazů: `/api/tags` vrací modely stažené doma a klíč
na něj netřeba, `/mgmt/v1/models` přidá modely zapnutých poskytovatelů. Když
druhý dotaz selže nebo vrátí něco nečekaného, zůstane aspoň to, co běží doma —
picker nikdy nezůstane prázdný.

Pod hláškou o spojení je rozbalovací *Co proxy odpověděla* se syrovou odpovědí
obou dotazů. Když v seznamu chybí model, o kterém víš, že v proxy je, koukni
sem — je z toho vidět, jestli ho proxy vůbec nabízí.

Nastavený model, který proxy nezná, hlásí stránka červeně. Přepis by na něm
spadl na `model not found`, tak ho vyber ze seznamu znovu a ulož.

### Uvažující modely

Model s vlastností `thinking` (v Ollamě jsou to `qwen3`, `qwen3-vl`, `gemma4`)
si nejdřív rozmyslí, co odpoví. Vypnout se to má parametrem `think: false`
a aplikace ho posílá — `gemma4` poslechne, `qwen3-vl` ne: v testu spolkl
11,5 tisíce znaků úvah, narazil na strop odpovědi a k přepisu se nedostal.
Na čtení stránek proto ber model, který neuvažuje (`deepseek-ocr`,
`strike-ocr`). Na skládání sady uvažování nevadí, jen to trvá.

### Modely na kartě

```bash
ollama pull deepseek-ocr        # čtení obrázků, ~6.7 GB (potřebuje Ollamu 0.13+)
ollama pull qwen3               # sestavení sady
```

Na čtení jde použít i obecný vision model (`gemma4:12b`, `qwen3-vl:8b`,
`minicpm-v`) — jen mu dej jiné zadání, viz výš.

**Když máš málo paměti na kartě, dej do obou polí tentýž lokální model.** Dva
různé se na 12 GB nevejdou současně a přenačítaly by se mezi krokem „přepis"
a „sestavení". Aplikace si u každého volání říká o `keep_alive` 30 minut,
takže model mezi stránkami z karty nevypadne.

**Uvažovací modely se na přepis nehodí.** Myšlenkový postup jim spolyká celý
kontext a k samotnému přepisu se nedostanou — vypadá to, jako by model po pár
minutách vrátil prázdno. Aplikace uvažování vypíná (`think: false`), ale ne
každý model to respektuje; když na takový narazíš, řekne ti to a poradí
sáhnout po jiném.

Aplikace se navíc před přepisem zeptá, jestli model umí obrázky, a textovému
modelu fotku vůbec nepošle. U komerčních modelů se ptát nemá koho, ty proxy
jen přeposílá.

## Nastavení v aplikaci

**Admin → 🔍 Skenování učebnic → ⚙️ Modely a zadání** — vyplň adresu proxy
a klíč. Jakmile spojení funguje, aplikace si sama načte seznam modelů
a nabídne ho v rozbalovacím seznamu, rozdělený na domácí a komerční.

### Velikost kontextu

Týká se modelů běžících doma. Ollama má ve výchozím stavu jen pár tisíc
tokenů kontextu a **co se nevejde, tiše zahodí** — u sady sestavené
z několika stránek by pak potichu chyběla poslední slovíčka. Aplikace si
proto kontext říká sama; nastavuje se ve stejném formuláři jako modely
a výchozí hodnota je 8192.

Větší kontext zabere víc paměti na kartě. Na 12 GB je 8192 rozumný začátek;
když máš dávky delší, zvyš ho a sleduj, jestli se model ještě vejde do VRAM.

Pod textem v *Tvorbě sad* je vždycky vidět odhad, kolik tokenů zabírá a kolik
je nastaveno — a když se to nemá šanci vejít, aplikace to řekne dřív, než dáš
*Sestavit JSON*. U komerčního modelu se na to nedá narazit, takže mlčí.

## Poznámka k autorským právům

Pro vlastní potřebu (§30 autorského zákona) si můžeš učebnici oskenovat a
procvičovat podle ní doma s vlastními dětmi. Pole *zdroj* je u každé sady
povinné právě proto, aby bylo vidět, odkud obsah je.

Celý portál je za přihlášením a to je to podstatné — obsah z učebnic se
nikam veřejně nedostane. Nedělej z něj veřejné demo ani režim pro hosty.
