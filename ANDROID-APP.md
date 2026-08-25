# Android aplikace (TWA)

Portál se dá zabalit do Android aplikace, která se objeví v seznamu aplikací
na telefonu a jde spravovat přes Family Link. Uvnitř je pořád tenhle web —
aplikace ho jen otevře na celou obrazovku bez adresního řádku (Trusted Web
Activity).

**Co z toho plyne prakticky:** aktualizace portálu se do telefonu dostane
sama. APK se přestavuje jen tehdy, když se mění samotný obal (ikona, název,
adresa) — ne když přibude nová hra.

---

## 1. Jednorázová příprava

### 1.1 Vytvoř podpisový klíč

Klíč je identita aplikace. Kdo ho má, může vydat aktualizaci pod stejnou
identitou; kdo ho ztratí, už žádnou aktualizaci nevydá — telefon odmítne
nainstalovat APK podepsané jiným klíčem. **Ulož si ho mimo repozitář**,
třeba do správce hesel.

```bash
cd android
./signing-key.sh create ~/typemaster-signing.jks
```

Skript se zeptá na heslo a pak vypíše všechno, co budeš potřebovat dál.
Potřebuje jen `keytool`, který je součástí každé Javy. Kdykoli si to můžeš
nechat vypsat znovu:

```bash
./signing-key.sh print ~/typemaster-signing.jks
```

### 1.2 Zapiš otisk na TrueNAS

Chrome pustí aplikaci na doménu bez adresního řádku jen tehdy, když ho o to
doména sama požádá — souborem `/.well-known/assetlinks.json`. Ten se generuje
z proměnné prostředí, takže stačí v konfiguraci aplikace na TrueNASu doplnit:

```
TWA_SHA256_FINGERPRINT=51:02:56:3E:…
```

a aplikaci restartovat. Ověř si, že to sedí:

```bash
curl https://vyuka.aleshulek.cz/.well-known/assetlinks.json
```

Musí se vrátit JSON s tvým otiskem. Když se vrátí `[]`, proměnná nedorazila.

> Otisk je veřejný údaj — publikuje se právě proto, aby si ho prohlížeč mohl
> ověřit. Tajné je jen heslo a soubor s klíčem.

### 1.3 Nastav GitHub secrets

`Settings → Secrets and variables → Actions → New repository secret`:

| Secret | Hodnota |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | dlouhý řetězec, který vypsal `signing-key.sh` |
| `ANDROID_KEYSTORE_PASSWORD` | heslo, které jsi ke klíči zadal |
| `ANDROID_KEY_ALIAS` | `typemaster` |

---

## 2. Postavit APK

`Actions → build-apk → Run workflow`. Volitelně vyplň verzi (třeba `1.2`),
jinak se odvodí z čísla běhu.

Hotové APK je ke stažení v artefaktech běhu (`typemaster-apk`). Když místo
ručního spuštění pushneš tag `v1.2`, workflow z něj navíc udělá release
a APK přiloží k němu.

Ve výpisu běhu je i řádek `Otisk podpisu (TWA_SHA256_FINGERPRINT): …` —
musí sedět s tím, co je na TrueNASu.

### Postavit lokálně

Potřebuješ Javu 17+ a Android SDK:

```bash
cd android
./gradlew assembleRelease \
  -PstoreFilePath=$HOME/typemaster-signing.jks \
  -PstorePassword=heslo -PkeyAlias=typemaster -PkeyPassword=heslo
```

Bez parametrů `-P…` se APK podepíše debug klíčem — dá se nainstalovat na
zkoušku, ale doména ho neuzná a ukáže se adresní řádek.

---

## 3. Instalace na telefon

APK se instaluje mimo Play, takže telefon bude chtít povolit instalaci
z neznámých zdrojů (u aplikace, ze které APK otevíráš — typicky Soubory
nebo Chrome).

**U dětského účtu pod Family Link tohle povolení může být zablokované.**
Instalaci pak provedeš tak, že ji rodič v Family Linku dočasně povolí, appku
nainstaluje a povolení zase odebere. Zkus to nejdřív na jednom telefonu —
podle verze Androidu a nastavení dohledu se to chová různě.

Po instalaci zkontroluj, že **není vidět adresní řádek**. Když vidět je,
znamená to, že se otisk v APK a v `assetlinks.json` neshodují — projdi krok
1.2. Chrome si výsledek ověření chvíli pamatuje, takže po opravě aplikaci
odinstaluj a nainstaluj znovu.

---

## 4. Family Link — co čekat

Ověřené je tohle: aplikace se po instalaci chová jako každá jiná — má ikonu
v seznamu aplikací a vlastní záznam v systému.

Co je potřeba vyzkoušet u vás doma, protože se to liší podle verze Androidu:

- **Časové limity a blokování** se v Family Linku nastavují nad seznamem
  nainstalovaných aplikací, kam sideloadovaná aplikace patří taky.
- **Schvalování stahování** je navázané na Play Store, takže na APK mimo Play
  se nevztahuje — proto ten krok s povolením neznámých zdrojů výše.

Kdyby se ukázalo, že potřebuješ plnou správu přes Play (schvalování, vzdálená
instalace), znamená to vývojářský účet za jednorázových 25 $ a splnění
pravidel pro aplikace určené dětem. Tenhle projekt je na to připravený —
stejné APK jde nahrát do Play, jen se místo vlastního klíče použije podpis
od Googlu a do `TWA_SHA256_FINGERPRINT` se doplní otisk z Play Console
(dají se tam vyplnit oba, oddělené čárkou).

---

## 5. Jak to uvnitř funguje

| Soubor | K čemu je |
|---|---|
| `.well-known/assetlinks.php` | vrací JSON s otiskem; `.htaccess` na něj přepisuje `/.well-known/assetlinks.json` |
| `android/app/src/main/AndroidManifest.xml` | spouští `LauncherActivity` z knihovny androidbrowserhelper, nastavuje barvy a úvodní obrazovku |
| `android/app/build.gradle` | adresa portálu, verze, podpis |
| `android/make-icons.php` | přepočítá launcher ikony z PWA ikon v kořeni projektu |
| `android/signing-key.sh` | vytvoří klíč a vypíše otisk i hodnoty pro GitHub |
| `.github/workflows/android-apk.yml` | build podepsaného APK |

Adresa portálu je na jednom místě — `siteUrl` v `android/app/build.gradle`.
Odtud se odvodí adresa, kterou aplikace otevírá, doména pro ověření
i `asset_statements` uvnitř APK. Když se doména změní, stačí přepsat tenhle
řádek (nebo předat `-PtwaHost=https://…`).
