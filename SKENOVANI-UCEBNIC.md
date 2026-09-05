# Skenování učebnic přes Ollamu

Vyfotíš stránky učebnice telefonem, nahraješ je do adminu a Ollama z nich
udělá sadu na procvičování. Všechno běží u tebe doma — fotky se nikam
neposílají.

## Jak to funguje

Postup má dva kroky a mezi nimi tebe:

1. **Přepis** — vision model přečte každou stránku zvlášť a vrátí text.
2. **Kontrola** — přepis si přečteš a opravíš, co model zkomolil.
3. **Sestavení** — textový model z opraveného přepisu složí JSON sady.

Ten prostřední krok tam není omylem. Malé modely mají s češtinou problém,
hlavně s háčky a čárkami. Když opravíš přepis, oprava se propíše do sady;
kdyby se přepis přeskočil, chyba by dojela až k dítěti.

Výsledný JSON se navíc **neuloží rovnou** — jde do stejného validátoru jako
ručně vložená sada. Když model vyrobí duplicitu nebo zapomene odpověď,
řekne ti to a do databáze se nedostane nic nezkontrolovaného.

## Co potřebuješ

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
ollama pull llama3.2-vision     # čtení obrázků, ~8 GB
ollama pull qwen2.5             # sestavení sady, ~5 GB
```

**Když máš málo paměti na kartě, dej do obou polí tentýž model.** Dva různé se
na 12 GB nevejdou současně a Ollama by je mezi krokem „přepis" a „sestavení"
pořád přenačítala. Gemma 3 od velikosti 4B nahoru umí obrázky i text, takže
pokryje obojí sama. K tomu se hodí `OLLAMA_KEEP_ALIVE=30m`, jinak Ollama model
po pěti minutách nečinnosti uvolní.

Na čtení obrázků jde použít i `minicpm-v` nebo `qwen2.5vl`. **Počítej s tím,
že tohle je slabé místo celého řetězu** — malé vision modely dělají v české
diakritice chyby a rozvržení stránky (sloupce, tabulky, číslování cvičení)
jim dělá potíže. Proto ta ruční kontrola mezi kroky.

Bez grafické karty to poběží, ale pomalu — klidně minuty na stránku. S GPU
jsou to jednotky sekund.

## Nastavení v aplikaci

**Admin → 🔍 Skenování učebnic** — nahoře vyplň adresu Ollamy a vyber oba
modely. Jakmile adresa sedí, aplikace si sama načte seznam stažených modelů
a nabídne ho v rozbalovacím seznamu.

### Velikost kontextu

Ollama má ve výchozím stavu jen pár tisíc tokenů kontextu a **co se nevejde,
tiše zahodí** — u sady sestavené z několika stránek by pak potichu chyběla
poslední slovíčka. Aplikace si proto kontext říká sama; nastavuje se ve stejném
formuláři jako modely a výchozí hodnota je 8192.

Větší kontext zabere víc paměti na kartě. Na 12 GB je 8192 rozumný začátek;
když máš dávky delší, zvyš ho a sleduj, jestli se model ještě vejde do VRAM.

Pod přepsaným textem je vždycky vidět odhad, kolik tokenů zabírá a kolik je
nastaveno — a když se to nemá šanci vejít, aplikace to řekne dřív, než dáš
*Sestavit JSON*.

## Nahrání stránek

1. Vyfoť stránky telefonem. Nemusíš je zmenšovat — prohlížeč to udělá sám
   (delší strana na 1600 px), takže se do Ollamy neposílají zbytečně velké
   obrázky.
2. Vyber je všechny naráz do pole **Stránky** a dej *Nahrát a přepsat*.
3. Přepis se rozjede sám, stránku po stránce, a u každé vidíš stav i čas.
   Stránka, která selhala (třeba když Ollama zrovna neběžela), má tlačítko
   **Znovu** — fotit ji podruhé nemusíš.

## Sestavení sady

Vyplň název, zdroj, předmět, typ a ročník a dej *Sestavit JSON*. Aplikace
ukáže výsledek i s případnými chybami. JSON si můžeš rovnou v poli upravit
a pak ho pošleš přes *Otevřít v importu sad* do finální kontroly a uložení.

Typ sady vybírej podle toho, co je na stránkách:

| Typ | Kdy |
|---|---|
| dvojice | dvousloupcový seznam slovíček, veličin, letopočtů |
| vyber | otázky s nabídnutými odpověďmi |
| doplnovacka | věty s vynechaným slovem |
| cteni | souvislý text a otázky k němu |

## Úklid

Fotky zabírají v databázi místo, takže se dávky po **14 dnech mažou samy**.
Smazat je můžeš i ručně v seznamu dole. Sady, které z nich vznikly, to
nijak neovlivní — ty jsou uložené zvlášť.

## Poznámka k autorským právům

Pro vlastní potřebu (§30 autorského zákona) si můžeš učebnici oskenovat a
procvičovat podle ní doma s vlastními dětmi. Pole *zdroj* je u každé sady
povinné právě proto, aby bylo vidět, odkud obsah je.

Celý portál je za přihlášením a to je to podstatné — obsah z učebnic se
nikam veřejně nedostane. Nedělej z něj veřejné demo ani režim pro hosty.
