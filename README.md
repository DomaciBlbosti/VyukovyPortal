# TypeMaster — Psaní všemi deseti

Webová aplikace pro trénink psaní na klávesnici. Apache / PHP 8+ / MySQL 8+.

## 🐳 TrueNAS / Docker (doporučeno)

Aplikace běží jako TrueNAS custom app se self-update z Gitu — viz
**[DEPLOY-TRUENAS.md](DEPLOY-TRUENAS.md)**. Zkráceně:

```bash
cp .env.example .env   # uprav hesla
docker compose up -d
```

Databáze i admin účet se založí samy, instalační průvodce se nepoužívá.

## 📱 Mobil a instalace jako aplikace (PWA)

Portál je optimalizovaný pro telefony a funguje jako **PWA** — na Androidu
(Chrome) se dá nainstalovat jako aplikace:

1. Otevři portál v Chrome (musí běžet na **HTTPS**).
2. Klepni na **📲 Instalovat** v menu, nebo v menu Chromu vyber
   „Přidat na plochu / Instalovat aplikaci“.
3. Aplikace se objeví na ploše s vlastní ikonou a běží celoobrazovkově.

Na iOS: Safari → Sdílet → „Přidat na plochu“.

## Rychlý start (klasický hosting)

### 1. Databáze
```sql
-- V phpMyAdmin nebo mysql CLI:
source schema.sql;
```

### 2. Konfigurace
Uprav `config/db.php`:
```php
define('DB_USER', 'tvuj_mysql_user');
define('DB_PASS', 'tvoje_heslo');
```

### 3. Nahrání na server
Nahraj celou složku do kořene webu (DocumentRoot), např. `/var/www/html/`.

### 4. Změna hesel uživatelů
Výchozí heslo pro oba účty je `password`. Změň je v MySQL:
```sql
UPDATE users SET password_hash = '$2y$10$...' WHERE username = 'uzivatel1';
```
Hash vygeneruješ v PHP:
```php
echo password_hash('nové_heslo', PASSWORD_BCRYPT);
```

---

## Struktura souborů

```
/
├── index.php           ← přihlášení
├── dashboard.php       ← rozcestník
├── stats.php           ← statistiky hráče
├── leaderboard.php     ← žebříček
├── logout.php
├── schema.sql          ← SQL schéma
├── .htaccess
├── config/
│   └── db.php          ← DB připojení (edituj!)
├── includes/
│   ├── auth.php        ← přihlašování, sessions
│   ├── header.php
│   └── footer.php
├── games/
│   └── classic.php     ← klasická hra (funguje)
├── css/
│   └── style.css
└── js/
    ├── app.js
    ├── typing_game.js  ← logika hry
    └── charts.js       ← grafy statistik
```

---

## Přidání nové hry

1. Vytvoř `games/nova_hra.php` (zkopíruj `classic.php` jako základ)
2. V `dashboard.php` přidej do pole `$games`:
```php
[
    'id'        => 'nova_hra',
    'title'     => 'Název hry',
    'icon'      => '🎮',
    'description' => 'Popis...',
    'url'       => '/games/nova_hra.php',
    'color'     => 'blue',
    'available' => true,
],
```
3. Výsledky ukládej přes `saveGameSession()` s `game_type = 'nova_hra'`.

---

## Požadavky
- PHP 8.1+
- MySQL 8.0+ (nebo MariaDB 10.6+)
- Apache s `mod_rewrite`, `mod_headers`
- PHP rozšíření: `pdo_mysql`
