# fluid – Bearbeiten direkt auf der Website für REDAXO 6

**fluid** bringt das Bearbeiten auf der Website nach REDAXO 6: Du klickst auf einen Text und tippst, fügst Blöcke ein,
verschiebst sie, änderst das Design – genau dort, wo Besucher die Seite sehen. Unter der Haube bleibt alles REDAXO:
Jeder Block ist ein echter Slice, mit Arbeitsversion, Rechten und Historie des Cores.

![Die Demo-Website mit fluid](docs/bilder/website.jpg)

> **Status: Prototyp.** fluid baut auf REDAXO 6 auf, das noch in Entwicklung ist (`redaxo/core` Branch 6.x). Zum
> Ausprobieren, Mitmachen und für eigene Experimente – noch nicht für Kundenseiten.

---

## Inhalt

1. [Was fluid kann](#was-fluid-kann)
2. [Schnellstart: in fünf Minuten zur eigenen Demo-Website](#schnellstart)
3. [Erste Schritte auf der Website](#erste-schritte-auf-der-website)
4. [Was in der Demo steckt](#was-in-der-demo-steckt)
5. [fluid in ein bestehendes REDAXO-6-Projekt einbauen](#fluid-in-ein-bestehendes-projekt-einbauen)
6. [Webserver: schöne Adressen](#webserver-schöne-adressen)
7. [Häufige Probleme](#häufige-probleme)
8. [Für Entwickler](#für-entwickler)
9. [Lizenz und Danke](#lizenz-und-danke)

---

## Was fluid kann

**Für Redakteure** – alles auf der Website, über eine Leiste oben:

- **Texte direkt auf der Seite** ändern, mit Formatierungsleiste; alle Felder eines Blocks in der Seitenleiste
- **Blöcke** einfügen, verschieben (ziehen oder ↑ ↓), verdoppeln, kopieren, online/offline – 24 Blöcke im Kit,
  vom Einstieg über Text + Bild, Zahlen, Fragen & Antworten, Preise und Karten bis zum Video
- **Layout und Greifer:** Spalten, Abstände, Textbreite und Aufteilung Bild/Text mit der Maus ziehen
- **Entwurf und Veröffentlichen:** Änderungen erst im Entwurf, dann „Veröffentlichen“; ältere Stände wiederherstellen
- **Seiten:** Seitenbaum mit Ziehen, neue Seiten (leer, als Kopie, aus einer Vorlage), Adresse und SEO-Angaben
- **Design:** fertige Design-Vorlagen (u. a. „REDAXO · Blau“) oder Farben, Schriften und Formen einzeln – mit
  Kontrastprüfung, live auf der Seite
- **Mehrsprachig:** Sprachwahl in der Leiste, Inhalte einer Sprache übernehmen und übersetzen, Sprachwahl für Besucher
- **Blog / News** mit Schlagworten, RSS und „Neuer Beitrag“ direkt in der Übersicht
- **Formulare** zum Zusammenklicken – auch mehrstufig, speichern und/oder per E-Mail, mit Bestätigung (YForm)
- **Daten aus YForm** als Karten oder Liste mit eigener Adresse je Eintrag – und Blöcke auch in Datensätzen
- **Videos** mit automatischem Vorschaubild und Untertiteln, **Karten** ohne Einwilligung, **Suche** mit Tippfehler-Toleranz

**Für Besucher:** schnelle, barrierearme Seiten, schöne Adressen, Sitemap, strukturierte Daten, keine Cookies für Karten.

**Für Entwickler:** Blöcke sind kleine PHP-Klassen mit Feldschema und View, Extension Points an allen wichtigen
Stellen, YForm-Tabellen und -Formulare als Klassen – mehr in der [Entwickler-Referenz](docs/entwickler.md).

| Formular in Schritten | Blöcke in einem YForm-Datensatz |
|---|---|
| ![Mehrstufiges Formular](docs/bilder/formular-schritte.jpg) | ![Blöcke eines Datensatzes bearbeiten](docs/bilder/bearbeiten-datensatz.jpg) |

---

## Schnellstart

Mit einem Befehl entsteht eine komplette Website: REDAXO 6, YForm 6, fluid und – wenn du möchtest – die Demo mit
allen Seiten, Beispieldaten, Bildern und dem Design „REDAXO · Blau“.

### 1. Das brauchst du

| | Version | So bekommst du es |
|---|---|---|
| **PHP** | 8.5 mit `pdo_mysql`, `pdo_sqlite`, `intl`, `mbstring`, `fileinfo`, `gd` oder `imagick` | Mac: [Herd](https://herd.laravel.com) oder `brew install php` · Windows: [Laragon](https://laragon.org) · Linux: Paketverwaltung |
| **MySQL oder MariaDB** | MySQL 8.4 / MariaDB 10.11 oder neuer | in Herd/Laragon/MAMP enthalten, sonst [DBngin](https://dbngin.com) (Mac) |
| **git** | beliebig | meist schon da: `git --version` |
| Composer | 2.9 oder neuer | **lädt das Skript selbst**, wenn deins fehlt oder älter ist |
| ffmpeg | optional | nur für automatische Video-Vorschaubilder |

Prüfen kannst du PHP mit `php -v` – dort sollte `8.5` stehen.

### 2. Installieren

Im Terminal in den Ordner wechseln, in dem die Website entstehen soll, dann:

```bash
curl -fsSLO https://raw.githubusercontent.com/FriendsOfREDAXO/fluid/main/install-dev.sh
bash install-dev.sh meine-seite
```

Das Skript fragt nach der Datenbank, falls es sich nicht mit `root` ohne Passwort verbinden kann. Du kannst sie auch
gleich mitgeben:

```bash
bash install-dev.sh meine-seite --db mysql://root:geheim@127.0.0.1:3306/meine_seite
```

Die Datenbank legt das Skript selbst an. Nach etwa einer Minute erscheint:

```
  Website:   http://127.0.0.1:8000/
  Backend:   http://127.0.0.1:8000/redaxo/
  Anmelden:  admin  /  (Passwort)
```

**Notier dir das Passwort** – es wird nirgends gespeichert. Der Server läuft dann schon; beenden mit `Strg + C`.

### Mit oder ohne Demo-Inhalte

| Befehl | Ergebnis |
|---|---|
| `bash install-dev.sh meine-seite` | **mit Demo:** alle Beispielseiten, Blog, Presse, Projekte, Formular, Bilder, Design „REDAXO · Blau“ |
| `bash install-dev.sh meine-seite --empty` | **ohne Inhalte:** die Website zeigt „Erste Schritte“ – dort legst du mit einem Klick eine leere Startseite **oder** doch die Beispielseiten an |

Weitere Optionen: `--port 8080` (anderer Port), `--admin name` (anderer Benutzername), `--no-start` (Server nicht
starten). Alle Optionen: `bash install-dev.sh --help`.

### 3. Später wieder starten

```bash
cd meine-seite
php -S 127.0.0.1:8000 -t public dev-router.php
```

Dann im Browser http://127.0.0.1:8000/ öffnen. Nutzt du Herd, Laragon, MAMP oder Apache, kannst du auch dort einen
Host auf den Ordner `public` zeigen lassen (siehe [Webserver](#webserver-schöne-adressen)).

---

## Erste Schritte auf der Website

1. **Anmelden:** http://127.0.0.1:8000/redaxo/ öffnen und mit `admin` und deinem Passwort anmelden.
2. **Zur Website:** Oben im Backend auf den Namen der Website klicken – oder http://127.0.0.1:8000/ öffnen. Weil du
   angemeldet bist, siehst du oben die **Leiste von fluid**.
3. **Bearbeiten:** In der Leiste auf **„Bearbeiten“**. Jetzt kannst du in jeden Text klicken und tippen. Über einem Block
   erscheint seine Leiste: **Bearbeiten** öffnet alle Felder in der Seitenleiste, ↑ ↓ verschiebt, ⋯ verdoppelt oder kopiert.
4. **Block einfügen:** Mit der Maus über einen Block fahren, unten auf das runde **+** klicken und einen Block wählen.
5. **Speichern und veröffentlichen:** **„Speichern“** legt die Änderungen im Entwurf ab – Besucher sehen noch die alte
   Fassung. **„Veröffentlichen“** bringt alles live.
6. **Design:** **„Design“** öffnet die Vorlagen und alle Einstellungen. Ein Klick auf eine Vorlage zeigt sie sofort;
   „Speichern“ übernimmt sie für die ganze Website.
7. **Mehr im Menü ⋯:** Seiten (Seitenbaum), Neue Seite, Seiteneinstellungen, Website (Name, Logo, Kontakt, Sprachen,
   Domains), Verlauf, Anfragen.

Im Backend findest du unter **AddOns → fluid** eine Statusseite, die prüft, ob alles richtig eingerichtet ist.

![Statusseite im Backend](docs/bilder/backend-status.jpg)

---

## Was in der Demo steckt

| Seite | Zeigt |
|---|---|
| **Start** | Einstieg, Merkmale, Zahlen, Layout mit Text und Zitat in Spalten, Handlungsaufruf |
| **Technik**, **Was sich ändert**, **Bearbeiten** | Schritte, Fließtext, Fragen & Antworten, Merkmale, Zitat, Spalten |
| **Bausteine** | weitere Blöcke des Kits: Logos, Bento, Reiter, Preise |
| **Daten** | YForm: Termine als Datenliste, Kontaktformular mit Postfach |
| **Presse** | Kurzmeldungen aus YForm – nach Datum, mit Blättern und eigener Adresse je Meldung |
| **Projekte** | Fallstudien: YForm-Einträge, deren Inhalt aus Blöcken besteht – direkt auf der Website bearbeitbar |
| **Neuigkeiten** | Blog mit Beiträgen, Schlagworten und RSS |
| **Anmeldung** | Formular-Baukasten mit drei Schritten (nicht im Menü: `/anmeldung`) |
| **Suche** | Suche über alle Seiten |

Die Beispiel-Tabellen (Termine, Anfragen, Meldungen, Projekte) liegen als YForm-Klassen in `src/YForm/` deines Projekts –
du kannst sie ändern oder löschen. Im Backend stehen sie unter „Daten“.

**Neu anfangen:** Willst du die Demo-Inhalte wieder loswerden, lösch die Seiten im Seitenbaum – oder setz die ganze
Installation neu auf (Ordner und Datenbank löschen, Skript erneut starten).

---

## fluid in ein bestehendes Projekt einbauen

Du hast schon ein REDAXO-6-Projekt (`composer create-project redaxo/project:6.x-dev`)? Dann reichen drei Schritte.
Solange fluid und YForm 6 noch nicht auf Packagist stehen, holt Composer sie direkt von GitHub:

```bash
composer config repositories.yform '{"type": "vcs", "url": "https://github.com/yakamara/yform", "no-api": true}'
composer config repositories.fluid '{"type": "vcs", "url": "https://github.com/FriendsOfREDAXO/fluid", "no-api": true}'
composer require yakamara/yform:6.x-dev@dev friendsofredaxo/fluid:*@dev

php bin/console migrate
```

YForm ist optional – ohne YForm fehlen nur Formulare, Datenlisten und das Postfach. Für die Bildbearbeitung auf der
Website gibt es das AddOn `friendsofredaxo/image_editor` (optional).

Danach entweder auf der Website „Erste Schritte“ nutzen oder in der Konsole:

```bash
# Beispiel-Tabellen der Demo (optional, für Daten/Presse/Projekte)
mkdir -p src/YForm && cp vendor/friendsofredaxo/fluid/examples/yform/*.php src/YForm/ && php bin/console migrate

# komplette Demo mit Bildern und Design „REDAXO · Blau“
php bin/console fluid:demo --set=redaxo6 --media --design=redaxo
```

`fluid:demo` legt Inhalte nur in einer **leeren** Installation an. In der Konsole kannst du mit `--page=projekte`
einzelne Demo-Seiten anhängen.

**Mail:** Formulare und Benachrichtigungen nutzen den Mailer des Cores – `MAILER_DSN` und `REX_MAILER_FROM` in
`.env.local` (das Installationsskript setzt `null://null`: Mails werden dann nicht verschickt).

**Nach Updates** (`composer update`): immer `php bin/console migrate` – das aktualisiert Tabellen und kopiert die
Assets neu.

---

## Webserver: schöne Adressen

fluid löst Adressen wie `/projekte/stadtwerke-musterstadt-1` selbst auf. Der Webserver muss deshalb alle Anfragen, zu
denen es keine Datei gibt, an `public/index.php` geben. Die Wurzel des Webservers ist immer der Ordner **`public`**.

**Eingebauter PHP-Server** (Entwicklung): `php -S 127.0.0.1:8000 -t public dev-router.php` – `dev-router.php` legt das
Installationsskript an.

**Apache** (`public/.htaccess` – das Skript ergänzt die Regeln):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

**nginx:**

```nginx
root /pfad/zum/projekt/public;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

**Herd / Valet / Laragon:** einen Host auf den Ordner `public` zeigen lassen – Rewrites sind dort schon eingerichtet.

---

## Häufige Probleme

**„PHP … ist zu alt“** – REDAXO 6 braucht PHP 8.5. Hast du mehrere PHP-Versionen, gib die richtige an:
`PHP=/pfad/zu/php8.5 bash install-dev.sh meine-seite`.

**„Keine Verbindung zur Datenbank“** – Läuft MySQL/MariaDB? Stimmen Benutzer, Passwort und Port? MAMP nutzt z. B.
Port 8889 und `root`/`root`: `--db mysql://root:root@127.0.0.1:8889/meine_seite`.

**Die Website hat keine Farben/Schriften** – Der Webserver zeigt nicht auf `public`. Beim PHP-Server `-t public`
nicht vergessen.

**Unterseiten liefern „404“, die Startseite geht** – Die Rewrite-Regeln fehlen (siehe [Webserver](#webserver-schöne-adressen)).

**„Could not authenticate against github.com“** – Composer fragt GitHub über die API ab. Das Skript nutzt deshalb
`"no-api": true`; trägst du die Repositories selbst ein, nimm dieselbe Schreibweise wie oben.

**„This is an invalid domain“ in der Browser-Konsole** – kommt von der Passkey-Anmeldung im Backend auf einer
IP-Adresse und ist harmlos. Mit `http://localhost:8000/` verschwindet die Meldung.

**Nach `composer update` sieht etwas alt aus** – `php bin/console migrate` ausführen (kopiert die Assets neu).

**Passwort vergessen** – `php bin/console user:set-password admin`.

---

## Für Entwickler

Alles Technische steht in der **[Entwickler-Referenz](docs/entwickler.md)**:

- eigene Blöcke als Modulklasse + View, alle Feldtypen und Block-Optionen
- Extension Points (`FLUID_BLOCK_DEFINITION`, `FLUID_BLOCK_VIEW`, `FLUID_SETTINGS_SCHEMA` …)
- YForm: Formular-Baukasten, Datenliste, YForm-Feld „Fluid-Blöcke“, Postfach
- Routing, Domains, SEO, Sprachen, Videos, Blog, Karten, Rechte, Konsole

Ein eigener Block in Kürze:

```php
// src/Block/Teaser.php
#[AsModule('fluid.teaser', 'Teaser')]
final class Teaser extends FluidBlock
{
    protected function define(): array
    {
        return ['label' => 'Teaser', 'group' => 'Inhalt', 'fields' => [
            ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
        ]];
    }
}
```

```php
<?php // src/Block/Teaser.view.php ?>
<div class="wrap">
  <h2<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
  <p<?= $b->edit('text', 'lines') ?>><?= nl2br(e($d['text']), false) ?></p>
</div>
```

`$b->edit('feld')` macht das Feld direkt auf der Website bearbeitbar – fertig ist der Block, mit Seitenleiste,
Entwurf, Rechten und Historie.

**Mitmachen:** Fehler und Ideen gern als [Issue](https://github.com/FriendsOfREDAXO/fluid/issues), Pull Requests
willkommen. Zum Entwickeln: Repo klonen und `FLUID_SOURCE=$PWD/fluid bash fluid/install-dev.sh fluid-dev`
(siehe [Mitentwickeln](docs/entwickler.md#mitentwickeln)).

---

## Lizenz und Danke

MIT – siehe [LICENSE](LICENSE).

- Konzept und Teile des Codes aus [KLXM Studio](https://github.com/KLXM-Opensource/studio) (MIT)
- [REDAXO](https://redaxo.org) und [YForm](https://github.com/yakamara/yform) von Yakamara und der REDAXO-Community
- Symbole: [Phosphor Icons](https://phosphoricons.com) (MIT) · Schriften: SIL Open Font License (Lizenzen in `assets/fonts/`)
- Karten: [MapLibre GL JS](https://maplibre.org) (BSD-3), [OpenFreeMap](https://openfreemap.org), Daten © OpenStreetMap-Mitwirkende (ODbL)
- Suche: [Loupe](https://github.com/loupe-php/loupe) (MIT)
- Das REDAXO-Logo in der Demo gehört dem REDAXO-Projekt; es kommt aus dem Core und wird nur in der Demo verwendet.
