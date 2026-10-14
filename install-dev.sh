#!/usr/bin/env bash
#
# fluid für REDAXO 6 – Entwicklungs-Installation mit einem Befehl
#
#   bash install-dev.sh                       Ordner „fluid-demo“, komplette Demo-Website
#   bash install-dev.sh meine-seite --empty   ohne Demo-Inhalte (die Website zeigt „Erste Schritte“)
#
# Optionen:
#   --demo | --empty        mit Demo-Inhalten (Standard) bzw. leer
#   --db URL                Datenbank, z. B. mysql://root:geheim@127.0.0.1:3306/fluid_demo
#                           (Standard: mysql://root@127.0.0.1:3306/<ordner>; auch per Umgebungsvariable DATABASE_URL)
#   --port N                Port des eingebauten PHP-Servers (Standard 8000)
#   --admin NAME            Benutzername des Admins (Standard admin); das Passwort wird erzeugt und am Ende angezeigt
#   --no-start              Server am Ende nicht starten
#
# Voraussetzungen: PHP 8.5 (pdo_mysql, pdo_sqlite, intl, mbstring, fileinfo, gd oder imagick), MySQL 8.4 oder
# MariaDB 10.11, git. Composer 2.9+ wird bei Bedarf automatisch geladen. NUR für die Entwicklung – nicht auf Servern.
#
# Lokale Quellen statt GitHub (für die Arbeit an fluid selbst):
#   FLUID_SOURCE=/pfad/zu/fluid  IMAGE_EDITOR_SOURCE=/pfad/zu/image_editor  YFORM_SOURCE=/pfad/zu/yform

set -euo pipefail

DIR="fluid-demo"
MODE="demo"
DB_URL="${DATABASE_URL:-}"
PORT=8000
ADMIN="admin"
START=1
while [ $# -gt 0 ]; do
  case "$1" in
    --demo) MODE="demo" ;;
    --empty) MODE="empty" ;;
    --db) DB_URL="$2"; shift ;;
    --port) PORT="$2"; shift ;;
    --admin) ADMIN="$2"; shift ;;
    --no-start) START=0 ;;
    -h|--help) sed -n '2,22p' "$0"; exit 0 ;;
    -*) echo "Unbekannte Option: $1 (Hilfe: --help)"; exit 1 ;;
    *) DIR="$1" ;;
  esac
  shift
done

FLUID_REPO="https://github.com/FriendsOfREDAXO/fluid"
IMAGE_EDITOR_REPO="https://github.com/FriendsOfREDAXO/image_editor"
YFORM_REPO="https://github.com/yakamara/yform"

b() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '  \033[32m✓\033[0m %s\n' "$*"; }
fail() { printf '\n\033[31m✗ %s\033[0m\n' "$*"; exit 1; }

# ------------------------------------------------------------------ 1. Voraussetzungen

b "1/7 Voraussetzungen prüfen"
PHP="${PHP:-php}"
command -v "$PHP" >/dev/null || fail "PHP nicht gefunden. Bitte PHP 8.5 installieren (z. B. Herd, MAMP, Homebrew: brew install php)."
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' || fail "PHP $("$PHP" -r 'echo PHP_VERSION;') ist zu alt – REDAXO 6 braucht PHP 8.5. Anderes PHP: PHP=/pfad/zu/php bash install-dev.sh"
ok "PHP $("$PHP" -r 'echo PHP_VERSION;')"
MISSING=$("$PHP" -r '$m=[]; foreach (["pdo_mysql","pdo_sqlite","intl","mbstring","fileinfo"] as $e) if (!extension_loaded($e)) $m[]=$e; if (!extension_loaded("gd") && !extension_loaded("imagick")) $m[]="gd oder imagick"; echo implode(", ", $m);')
[ -z "$MISSING" ] || fail "PHP-Erweiterungen fehlen: $MISSING"
ok "PHP-Erweiterungen"
command -v git >/dev/null || fail "git nicht gefunden (Composer braucht es für YForm 6)."
ok "git"
[ -e "$DIR" ] && fail "Ordner „${DIR}“ gibt es schon – bitte einen anderen Namen angeben: bash install-dev.sh anderer-name"

# Composer 2.9+ (das Composer-Plugin von REDAXO 6 verlangt es); sonst aktuelle Fassung laden
COMPOSER=""
if command -v composer >/dev/null && composer --version 2>/dev/null | "$PHP" -r '$v = fgets(STDIN); exit(preg_match("~(\d+\.\d+\.\d+)~", (string) $v, $m) && version_compare($m[1], "2.9.0", ">=") ? 0 : 1);'; then
  COMPOSER="composer"
else
  CACHE="${XDG_CACHE_HOME:-$HOME/.cache}/fluid-install"
  mkdir -p "$CACHE"
  if [ ! -f "$CACHE/composer.phar" ]; then
    echo "  Composer 2.9+ fehlt – lade die aktuelle Fassung nach $CACHE …"
    "$PHP" -r 'copy("https://getcomposer.org/download/latest-stable/composer.phar", $argv[1]) || exit(1);' "$CACHE/composer.phar" || fail "Composer konnte nicht geladen werden."
  fi
  COMPOSER="$PHP $CACHE/composer.phar"
fi
ok "$($COMPOSER --version 2>/dev/null | head -1)"

# Datenbank: Verbindung prüfen, Datenbank anlegen
SLUG=$(echo "$DIR" | tr 'A-Z-' 'a-z_' | tr -cd 'a-z0-9_')
DB_URL="${DB_URL:-mysql://root@127.0.0.1:3306/${SLUG:-fluid_demo}}"
db_check() {
  "$PHP" -r '
    $u = parse_url($argv[1]);
    if (!$u || ($u["scheme"] ?? "") !== "mysql" || empty($u["path"])) { fwrite(STDERR, "Ungültige Adresse – Beispiel: mysql://root:geheim@127.0.0.1:3306/fluid_demo\n"); exit(2); }
    try {
      $pdo = new PDO("mysql:host=" . ($u["host"] ?? "127.0.0.1") . ";port=" . ($u["port"] ?? 3306), urldecode($u["user"] ?? "root"), urldecode($u["pass"] ?? ""));
      $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "", trim($u["path"], "/")) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }' "$1"
}
while ! db_check "$DB_URL"; do
  echo "  Keine Verbindung zur Datenbank mit $DB_URL"
  [ -t 0 ] || fail "Bitte die Datenbank angeben: --db mysql://benutzer:passwort@host:3306/datenbank"
  read -r -p "  Datenbank-Adresse (mysql://benutzer:passwort@host:port/datenbank): " DB_URL
done
ok "Datenbank $(echo "$DB_URL" | sed -E 's#//([^:@]+):[^@]*@#//\1:***@#')"

# ------------------------------------------------------------------ 2. REDAXO 6

b "2/7 REDAXO 6 installieren (composer create-project redaxo/project)"
$COMPOSER create-project redaxo/project:6.x-dev "$DIR" --no-interaction --quiet
cd "$DIR"
ok "REDAXO 6 in ./$DIR"

# ------------------------------------------------------------------ 3. YForm 6, fluid, image_editor

b "3/7 YForm 6, fluid und image_editor hinzufügen"
repo() {   # Name, lokale Quelle (optional), GitHub-Adresse
  if [ -n "$2" ]; then
    $COMPOSER config "repositories.$1" "{\"type\": \"path\", \"url\": \"$2\", \"options\": {\"symlink\": false}}"
  else
    # no-api: per git klonen statt über die GitHub-API (die verlangt ohne Token schnell eine Anmeldung)
    $COMPOSER config "repositories.$1" "{\"type\": \"vcs\", \"url\": \"$3\", \"no-api\": true}"
  fi
}
repo yform "${YFORM_SOURCE:-}" "$YFORM_REPO"
repo fluid "${FLUID_SOURCE:-}" "$FLUID_REPO"
PACKAGES="yakamara/yform:6.x-dev@dev friendsofredaxo/fluid:*@dev"
if [ -n "${IMAGE_EDITOR_SOURCE:-}" ] || git ls-remote "$IMAGE_EDITOR_REPO" >/dev/null 2>&1; then
  repo image_editor "${IMAGE_EDITOR_SOURCE:-}" "$IMAGE_EDITOR_REPO"
  PACKAGES="$PACKAGES friendsofredaxo/image_editor:*@dev"
fi
# shellcheck disable=SC2086
$COMPOSER require $PACKAGES --no-interaction --quiet
ok "$(echo "$PACKAGES" | sed 's/:[^ ]*//g')"

# ------------------------------------------------------------------ 4. Einstellungen

b "4/7 Einstellungen (.env.local)"
cat > .env.local <<EOF
DATABASE_URL='$DB_URL'
REX_INSTANCE_NAME="fluid Demo"
# Entwicklung: Mails werden nicht verschickt. Für echte Mails z. B. MAILER_DSN=smtp://benutzer:passwort@smtp.example.org:587
MAILER_DSN="null://null"
REX_MAILER_FROM="fluid Demo <noreply@example.org>"
EOF
ok ".env.local geschrieben"

# ------------------------------------------------------------------ 5. Setup

b "5/7 REDAXO einrichten"
PASSWORD=$("$PHP" -r 'echo rtrim(strtr(base64_encode(random_bytes(12)), "+/", "-_"), "=");')
"$PHP" bin/console setup -n --lang=de_de --agree-license --db-createdb=no --db-setup=normal --admin-username="$ADMIN" --admin-password="$PASSWORD" >/dev/null
ok "Admin „${ADMIN}“ angelegt"
if [ "$MODE" = "demo" ]; then
  # Beispiel-Tabellen der Demo (YForm-Klassen im Projekt)
  mkdir -p src/YForm
  for f in vendor/friendsofredaxo/fluid/examples/yform/*.php; do cp "$f" src/YForm/; done
  $COMPOSER dump-autoload --quiet
  ok "YForm-Beispieltabellen nach src/YForm kopiert"
fi
"$PHP" bin/console migrate -n >/dev/null
ok "AddOns installiert, Tabellen angelegt (console migrate)"

# ------------------------------------------------------------------ 6. Inhalte

b "6/7 Inhalte"
if [ "$MODE" = "demo" ]; then
  DEMO_LOG=$(mktemp)
  if ! "$PHP" bin/console fluid:demo --set=redaxo6 --media --design=redaxo -n > "$DEMO_LOG" 2>&1; then
    cat "$DEMO_LOG"
    fail "Die Demo-Inhalte konnten nicht angelegt werden (siehe oben). REDAXO und fluid sind installiert – „php bin/console fluid:demo --set=redaxo6 --media --design=redaxo“ lässt sich wiederholen."
  fi
  grep -E '^ *(! )?\[(OK|NOTE)\]' "$DEMO_LOG" | sed -E 's/^ *(! )?\[(OK|NOTE)\] */    /' || true
  ok "Demo-Website angelegt"
else
  ok "Keine Inhalte – die Website zeigt „Erste Schritte“ (Startseite oder Demo mit einem Klick)"
fi

# Webserver: schöne Adressen. Apache: .htaccess, eingebauter PHP-Server: dev-router.php
if ! grep -q "RewriteRule" public/.htaccess 2>/dev/null; then
  cat >> public/.htaccess <<'EOF'

# fluid: schöne Adressen – alles ohne Datei an index.php
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.php [L]
</IfModule>
EOF
fi
cat > dev-router.php <<'EOF'
<?php
// Nur Entwicklung: Router für den eingebauten PHP-Server (php -S 127.0.0.1:8000 -t public dev-router.php)
// Dateien direkt, /redaxo/ ins Backend, alles andere an die Website (schöne Adressen von fluid).
$root = __DIR__ . '/public';
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ('/redaxo' === $path) {
    header('Location: /redaxo/', true, 301);
    return true;
}
if ('/' !== $path && is_file($root . $path) && !str_ends_with($path, '.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $root . $path;
    return false;
}
$backend = str_starts_with($path, '/redaxo/');
$_SERVER['SCRIPT_NAME'] = $backend ? '/redaxo/index.php' : '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . $_SERVER['SCRIPT_NAME'];
chdir(dirname($_SERVER['SCRIPT_FILENAME']));
require $_SERVER['SCRIPT_FILENAME'];
EOF
ok "Webserver: public/.htaccess (Apache) und dev-router.php (PHP-Server)"

# ------------------------------------------------------------------ 7. Fertig

b "7/7 Fertig"
cat <<EOF

  Website:   http://127.0.0.1:$PORT/
  Backend:   http://127.0.0.1:$PORT/redaxo/
  Anmelden:  $ADMIN  /  $PASSWORD     (bitte notieren – das Passwort wird nicht gespeichert)

  Danach auf der Website oben „Bearbeiten“ wählen.
  Server später wieder starten:   cd $DIR && php -S 127.0.0.1:$PORT -t public dev-router.php
  Nach Updates (composer update):  php bin/console migrate

EOF
if [ "$START" = "1" ]; then
  echo "  Server läuft – beenden mit Strg+C"
  exec "$PHP" -d upload_max_filesize=64M -d post_max_size=64M -S "127.0.0.1:$PORT" -t public dev-router.php
fi
