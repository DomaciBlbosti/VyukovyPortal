#!/usr/bin/env bash
#
# Podpisový klíč pro Android aplikaci.
#
#   ./signing-key.sh create <soubor.jks>   vytvoří nový klíč
#   ./signing-key.sh print  <soubor.jks>   vypíše otisk a tajné hodnoty pro GitHub
#
# Klíč nikdy nedávej do repozitáře. Kdo ho má, může vydat aktualizaci pod
# identitou téhle aplikace; kdo ho ztratí, už žádnou aktualizaci nevydá.
set -euo pipefail

ALIAS="${KEY_ALIAS:-typemaster}"

usage() { sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 1; }

# Otisk ve tvaru AB:CD:… — přesně jak ho čeká assetlinks.json
fingerprint() {
    keytool -list -v -keystore "$1" -alias "$ALIAS" -storepass "$2" 2>/dev/null \
        | awk -F': ' '/SHA256:/ {print $2; exit}' | tr -d '[:space:]'
}

cmd="${1:-}"; store="${2:-}"
[ -z "$cmd" ] || [ -z "$store" ] && usage

case "$cmd" in
create)
    [ -e "$store" ] && { echo "Soubor $store už existuje — nechci ho přepsat."; exit 1; }
    read -rsp "Heslo ke klíči (zapamatuj si ho): " pass; echo
    read -rsp "Heslo znovu: " pass2; echo
    [ "$pass" != "$pass2" ] && { echo "Hesla se neshodují."; exit 1; }
    [ ${#pass} -lt 6 ] && { echo "Heslo musí mít aspoň 6 znaků."; exit 1; }

    keytool -genkeypair -v -keystore "$store" -alias "$ALIAS" \
        -keyalg RSA -keysize 4096 -validity 10000 \
        -storepass "$pass" -keypass "$pass" \
        -dname "CN=TypeMaster, O=DomaciBlbosti, C=CZ" > /dev/null
    echo "Klíč uložen do $store — ulož si ho někam mimo repozitář (třeba do správce hesel)."
    echo
    "$0" print "$store" <<< "$pass"
    ;;
print)
    [ ! -e "$store" ] && { echo "Soubor $store neexistuje."; exit 1; }
    if [ -t 0 ]; then read -rsp "Heslo ke klíči: " pass; echo; else read -r pass; fi

    fp="$(fingerprint "$store" "$pass")"
    [ -z "$fp" ] && { echo "Nepodařilo se přečíst klíč — sedí heslo a alias ($ALIAS)?"; exit 1; }

    cat <<TXT

── Na TrueNAS do proměnných prostředí aplikace ──────────────────
TWA_SHA256_FINGERPRINT=$fp

── Do GitHubu: Settings → Secrets and variables → Actions ───────
ANDROID_KEYSTORE_BASE64   $(base64 -w0 "$store" | head -c 40)…  (celá hodnota níže)
ANDROID_KEYSTORE_PASSWORD (heslo, které jsi zadal)
ANDROID_KEY_ALIAS         $ALIAS

Celý obsah ANDROID_KEYSTORE_BASE64:

$(base64 -w0 "$store")

TXT
    ;;
*) usage ;;
esac
