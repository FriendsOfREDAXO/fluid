# Routing (Rewriter)

Schöne Adressen für REDAXO 6: Seiten, Einträge eigener Quellen, Domains, Weiterleitungen, 404-Protokoll, sitemap.xml,
robots.txt und Seitenkopf (Canonical, hreflang, Open Graph, JSON-LD). Benutzung: `docs/entwickler.md`, „Adressen,
Domains, SEO“.

## Regeln für diesen Ordner

- **Kennt nichts von fluid.** Kein `use FriendsOfRedaxo\Fluid\…` außerhalb von `…\Routing`. fluid hängt sich an wie jedes
  andere AddOn: über Attribute, Extension Points und die Haken des Routers.
- **REDAXO-6-Stil:** Einstellungen als unveränderliche Objekte im Projekt (`Router::$config`, `Head::$config`), Quellen und
  Endpunkte als Attribute (Klassenerkennung des Cores), Extension Points als Klassen, Erweiterungen am Core per
  `#[AsExtension]`.
- **Kein Backend.** Was sich nur über eine Oberfläche einstellen ließe, gehört dem AddOn, das den Rewriter nutzt (fluid:
  Domains, Weiterleitungen, SEO-Felder der Seite).

## Als eigenes AddOn herauslösen

1. Ordner in ein Paket verschieben (Namespace z. B. `FriendsOfRedaxo\Routing`), Tabellen `rex_fluid_route*` umbenennen
   (Migration) oder behalten.
2. Addon-Klasse: `install()` → `Schema::install()`, `getOwnedSchema()` → `Schema::TABLES`, `boot()` → `Router::boot()`.
3. Alles andere (Auflösen, ArticleUrl, Neuaufbau, Endpunkte, Quellen, Konsole) hängt schon per Attribut am Core.

## Durch yrewrite + ein URL-AddOn ersetzen

Was AddOns nutzen, und wo es dann herkäme:

| Hier | yrewrite 3.x / URL-AddOn |
|---|---|
| `UrlFormat` (Endung) | `Scheme::setSuffix()` bzw. eigenes `Scheme` |
| Slug je Seite und Sprache, fest beim Umbenennen | eigene URL je Artikel (`yrewrite_url`, „CUSTOM“) – fester Slug fehlt noch (Vorschlag yrewrite#644) |
| `RouteSource`, `TableSource` + Attribute | Profile des URL-AddOns (Tabelle, Spalten, Seite) |
| `Router::urlFor()`, `Router::current()` | URL-AddOn: URL eines Datensatzes, aktueller Datensatz |
| `Router::resolver()`, `RouteMatch` | `YREWRITE_PREPARE` (gibt bisher nur die Artikel-ID zurück – Vorschlag yrewrite#644) |
| `Domains` (Haupt-, Alias-, Landing-Domain) | Domains von yrewrite (Landing für einen Artikel fehlt – yrewrite#644) |
| `Redirects` (Platzhalter, 410, Treffer, 404-Protokoll) | Weiterleitungen von yrewrite (Platzhalter, 410, Treffer fehlen – yrewrite#644) |
| `Sitemap`, `SitemapEntries` | `YREWRITE_SITEMAP`, `YREWRITE_DOMAIN_SITEMAP` |
| `Robots`, `RobotsTxt` | `YREWRITE_ROBOTS` (yrewrite#642) |
| `Seo\Head`, `PageMeta`, `ArticleMeta`, `PageMetaResolved` | `Seo::getTags()`, `YREWRITE_SEO_TAGS`, `YREWRITE_CANONICAL_URL`, `YREWRITE_HREFLANG_TAGS` |
| `#[AsEndpoint]` | eigene Routen des AddOns (z. B. `PACKAGES_INCLUDED`) |

Für den Wechsel: `RoutingConfig(enabled: false)` schaltet den Rewriter ab. Ein Adapter bildet dann `Router::urlFor()`,
`Router::current()`, `Head::render()` und die Quellen auf yrewrite/URL-AddOn ab – AddOns, die nur diese Schnittstelle
nutzen, ändern sich nicht.
