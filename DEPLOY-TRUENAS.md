# Nasazení TypeMaster na TrueNAS + self-update z Gitu

Stejný princip jako Kuchařka: obraz nese jen PHP/Apache toolchain, vlastní kód
si kontejner **naklonuje z Gitu do volume** a při každém restartu udělá
`git pull` (self-update). Instalační průvodce (`install.php`) se v Dockeru
nepoužívá — všechno se nastaví env proměnnými a databáze se založí sama.

## 1. Postav image (jednorázově)

**Varianta A — GitHub Actions (doporučeno):** po pushi `Dockerfile` /
`docker/**` do `main` postaví workflow image do
`ghcr.io/domaciblbosti/vyukovyportal:latest`. Aby ho TrueNAS mohl stáhnout,
nastav package na GitHubu jako **public** (Package settings → Change visibility),
nebo se na NASu přihlas `docker login ghcr.io`.

**Varianta B — lokálně na TrueNAS:**
```bash
git clone https://github.com/DomaciBlbosti/VyukovyPortal.git && cd VyukovyPortal
docker build -t ghcr.io/domaciblbosti/vyukovyportal:latest .
```

## 2. Spusť na TrueNAS

**Přes Apps UI (custom app / Install via YAML):** vlož obsah `TrueNasAPP.yaml`
a uprav `MARIADB_PASSWORD` + `DB_PASSWORD` (musí být stejné), `ADMIN_PASSWORD`
a port. TrueNAS UI nečte `.env`, hodnoty patří přímo do `environment`.

**Přes docker compose (shell):**
```bash
cat > .env <<ENV
DB_PASSWORD=neco-silneho
DB_ROOT_PASSWORD=neco-jeste-silnejsiho
ADMIN_PASSWORD=neco-tajneho
APP_PORT=8090
ENV
docker compose up -d
```

- První start: kontejner si naklonuje repo, počká na MariaDB, založí tabulky
  ze `schema.sql` a vytvoří admin účet (`ADMIN_USERNAME`/`ADMIN_PASSWORD`;
  bez nastaveného hesla vznikne `admin` / `admin123` — **hned ho změň**
  v aplikaci přes „Změna hesla“).
- Web běží na `http://truenas:8090`. Sleduj start přes
  `docker compose logs -f app`.

## 3. Aktualizace z Gitu

1. Na PC commitneš a pushneš novou verzi do `main`.
2. Na TrueNAS restartuješ appku (Apps → Restart, nebo
   `docker compose restart app`).
3. Entrypoint udělá `git pull` a nastartuje novou verzi. Data v MariaDB
   zůstávají (volume `db_data`), kód žije ve volume `app_code`.

> `UPDATE_ON_START=false` self-update vypne (kontejner pak jede pořád stejný
> commit). Jinou větev než `main` vybereš přes `REPO_BRANCH`.

## Env proměnné

| Proměnná | Výchozí | Význam |
|---|---|---|
| `DB_HOST` / `DB_PORT` | `db` / `3306` | adresa MariaDB |
| `DB_NAME` / `DB_USER` / `DB_PASSWORD` | `typemaster` / `typemaster` / — | přístup do DB |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | `admin` / `admin123` | poč. admin (jen do prázdné DB) |
| `BASE_URL` | *(prázdné = kořen)* | cesta, když app běží za proxy pod prefixem |
| `UPDATE_ON_START` | `true` | `git pull` při startu kontejneru |
| `REPO_URL` / `REPO_BRANCH` | repo / `main` | odkud se kód tahá |

## Zálohování

Stačí zálohovat volume `db_data` (databáze). Volume `app_code` je jen klon
repa — obnoví se samo.
