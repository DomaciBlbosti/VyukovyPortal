# TypeMaster — Instalace

## Rychlý start

1. **Nahraj soubory** na webový server (Apache + PHP 8.0+ + MySQL/MariaDB)
2. **Otevři prohlížeč** a jdi na `http://tvujserver.cz/typemaster/install.php`
3. **Projdi průvodcem** (4 kroky):
   - Kontrola požadavků
   - Nastavení databáze
   - Vytvoření admin účtu
   - Hotovo
4. **Smaž `install.php`** po úspěšné instalaci

## Požadavky serveru

| Požadavek | Min. verze |
|-----------|-----------|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PHP rozšíření | PDO, pdo_mysql, mbstring |
| Apache modul | mod_rewrite (volitelné) |

## Apache alias (WAMP, XAMPP)

Pro provoz jako `/typemaster` místo `localhost`:

```apacheconf
Alias /typemaster "C:/wamp64/www/typemaster"
<Directory "C:/wamp64/www/typemaster">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

## Bezpečnost po instalaci

- ☑ Smaž nebo přejmenuj `install.php`  
- ☑ Složka `config/` je chráněna `.htaccess`  
- ☑ `config/installed.lock` brání opakované instalaci

## Struktura složek

```
typemaster/
├── install.php        ← Spusť jednou, pak smaž
├── index.php          ← Přihlášení
├── dashboard.php      ← Rozcestník
├── admin/index.php    ← Admin panel (jen pro adminy)
├── config/
│   ├── db.php         ← Generuje install.php (nepřepisuj ručně)
│   ├── app.php        ← BASE_URL a další nastavení
│   └── installed.lock ← Zámek po instalaci
├── games/             ← Herní moduly
├── js/                ← JavaScript
└── css/               ← Styly
```
