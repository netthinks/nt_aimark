# Entwicklung

## Abhängigkeiten installieren

Die Extension bringt eine eigene `composer.json` mit; Vendor-Verzeichnis ist
`.Build/vendor`.

```bash
composer install
```

## Prüfungen

```bash
composer ci:php:cs         # PHP-CS-Fixer, PER-CS 2.0
composer fix:php:cs        # ... und automatisch korrigieren
composer ci:php:stan       # PHPStan Level 8, ohne Baseline
composer ci:tests:unit
composer ci:tests:functional
```

Barrierefreiheitstests (Playwright + axe-core, WCAG 2.1 AA):

```bash
npm ci
npx playwright install chromium
npx playwright test
```

Sie laufen gegen eine statische Fixture unter
`Tests/Acceptance/fixtures/labelled-page.html` und brauchen deshalb weder
Datenbank noch TYPO3-Boot. Damit das etwas beweist, prüft der funktionale
Test `MarkupFixtureTest`, dass die Fixture noch genau das Markup enthält,
das `LabelRenderService` erzeugt — wer das Fluid-Template ändert, bekommt
dort einen Fehlschlag, bis die Fixture nachgezogen ist.

Die Fixture wird über einen kleinen Node-Server ausgeliefert
(`Tests/Acceptance/server.mjs`), nicht über `file://`: Browser laden
ES-Module nicht von `file://`, die Seite käme ohne ihr JavaScript hoch und
die Tastaturtests würden aus dem falschen Grund bestehen.

Funktionale Tests brauchen eine Datenbank:

```bash
typo3DatabaseDriver=pdo_mysql \
typo3DatabaseHost=db typo3DatabasePort=3306 \
typo3DatabaseUsername=root typo3DatabasePassword=root \
typo3DatabaseName=func_aimark \
composer ci:tests:functional
```

Im DDEV-Kontext von `netthinks-14`:

```bash
ddev exec bash -c "cd packages/nt-aimark && composer ci:tests:unit"
```

## Konventionen

- **Codestil:** PER-CS 2.0 (`(int) $value` mit Leerzeichen). Weicht von der
  TYPO3-CGL ab, wird aber von der CI erzwungen — nicht dagegen anschreiben.
- **Sprache:** Klassen, Variablen und Kommentare englisch. Labels und
  Dokumentation deutsch.
- **XLIFF:** `locallang*.xlf` ist die englische Quellsprache,
  `de.locallang*.xlf` die Übersetzung. Das ist die TYPO3-Konvention; eine
  Datei `en.locallang*.xlf` würde nie geladen, da TYPO3 `en` als
  Standardsprache auflöst.
- **Kommentare** beschreiben das Warum, nicht das Was.
- **Keine XCLASS-Overrides**, keine deprecated Core-API.

## Fallstricke, die schon aufgetreten sind

- `displayCond` wird in TYPO3 v14 ausschließlich serverseitig ausgewertet.
  Abhängige Felder erscheinen nach dem Speichern, nicht schon bei der
  Auswahl im Formular.
- Tabellen **ohne TCA** bekommen vom Schema-Migrator kein `uid` und keinen
  Primärschlüssel. `tx_ntaimark_audit` deklariert beides deshalb explizit in
  `ext_tables.sql`.
- `EXT:`-Pfade sind in Unit-Tests nicht auflösbar, weil kein Package
  registriert ist. Der `IconResolverService` nimmt sein Verzeichnis deshalb
  über den Konstruktor entgegen.
- Fluid-`f:translate` braucht am Request die Attribute `applicationType`
  (als `int`, nicht als Enum-Case), `site` und `language`. Funktionale Tests
  müssen einen entsprechenden Request bauen.

## CLI

```bash
vendor/bin/typo3 aimark:scan --dry-run --limit=50
vendor/bin/typo3 aimark:scan --storage=1 --force
vendor/bin/typo3 aimark:report --limit=25 --email=redaktion@example.com
vendor/bin/typo3 aimark:verify --dry-run
```

Alle drei sind über den Scheduler planbar. `--force` beim Scan frischt
bestehende Vorschläge auf und rührt bestätigte Datensätze nicht an — die
Erkennung darf einen Menschen nicht überstimmen, unabhängig von den Optionen.

## Release

Vor dem Tag:

1. Version in `ext_emconf.php` setzen.
2. `CHANGELOG.md` unter der **konkreten Versionsnummer** ergänzen — nicht in
   `[Unreleased]` sammeln lassen.
3. Betroffene Dokumentation aktualisieren.

Dann:

```bash
git tag -a v1.0.0 -m "Release 1.0.0"
git push origin v1.0.0
```

Der Workflow `.github/workflows/publish-ter.yml` läuft daraufhin an. Er
prüft zuerst Codestil, statische Analyse und Unit-Tests, danach:

- ob die Version in `ext_emconf.php` mit dem Tag übereinstimmt,
- ob `CHANGELOG.md` einen Eintrag für diese Version enthält,

und veröffentlicht erst dann über `typo3/tailor` im TER.

### Einmalig vor dem ersten Release

| Schritt | Wo |
|---|---|
| Extension-Key `nt_aimark` reservieren | <https://extensions.typo3.org/> |
| Access-Token erzeugen (Scope „Extension write") | TYPO3-Konto → *Access Tokens* |
| Token als Secret `TYPO3_API_TOKEN` hinterlegen | GitHub → *Settings → Secrets and variables → Actions* |

Ohne das Secret bricht der Workflow mit einer klaren Meldung ab, statt
stillschweigend nichts zu tun.

Der Workflow lässt sich auch manuell mit einer Versionsnummer starten
(*Actions → Publish to TER → Run workflow*), etwa um eine fehlgeschlagene
Veröffentlichung zu wiederholen, ohne neu zu taggen.

## Bekannte Hinweise

TYPO3 14.3 verwarnt `ext_emconf.php` als veraltet. Die Datei bleibt bestehen,
solange TYPO3 13.4 unterstützt wird. Der Hinweis entsteht beim Bau des
Package-Artefakts, nicht bei jedem Request.
