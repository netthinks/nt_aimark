# CLAUDE.md — Build-Anweisung für `nt_aimark`

Dieses Dokument ist die vollständige, selbsttragende Arbeitsgrundlage. Es setzt keine Kenntnis vorheriger Gespräche voraus. Alles, was zur Umsetzung nötig ist, steht hier.

---

## 0. Auftrag in einem Satz

Baue eine TYPO3-Extension, mit der Redaktionen den KI-Anteil an Medien und Texten erfassen, diesen Status barrierefrei im Frontend kennzeichnen und die getroffenen Entscheidungen nachweisbar dokumentieren können — entsprechend den Anforderungen aus Art. 50 der Verordnung (EU) 2024/1689 (EU-KI-Verordnung / AI Act).

**Extension-Key:** `nt_aimark`
**Composer:** `netthinks/nt-aimark`
**Vertriebsmodell:** Dieses Repository ist das **freie Kernpaket** (TER, Packagist). Kostenpflichtige Zusatzfunktionen erscheinen später als **eigenes Composer-Paket** `netthinks/nt-aimark-pro` über ein privates Satis-Repository. Was daraus für die Umsetzung folgt, steht in Abschnitt 2a — bitte vor Beginn lesen.
**PHP-Namespace:** `NetThinks\NtAimark\`
**Zielversionen:** TYPO3 13.4 LTS und 14 LTS, PHP 8.2–8.4
**Lizenz:** GPL-2.0-or-later
**Sprache im Code:** Englisch (Klassennamen, Variablen, Kommentare). **Sprache in Labels, Dokumentation und README: Deutsch**, englische Übersetzung in `en.xlf` ergänzen.

---

## 1. Rechtlicher Kontext — nur so viel, wie für Designentscheidungen nötig

Art. 50 KI-VO gilt seit dem **2. August 2026**. Die Extension muss zwei grundverschiedene Rollen sauber trennen:

### Betreiber (Art. 50 Abs. 4) — der Regelfall für TYPO3-Websites
Wer generative KI beruflich einsetzt, um Inhalte zu erzeugen, muss offenlegen bei:

1. **Deepfakes** — KI-erzeugte oder manipulierte Bild-, Audio- oder Videoinhalte, die realistisch und authentisch wirken. Die Offenlegung muss **spätestens beim Erstkontakt** erfolgen, klar erkennbar und **ohne besondere technische Hilfsmittel**. Eine rein unsichtbare Markierung genügt ausdrücklich nicht.
2. **Texte zu Angelegenheiten von öffentlichem Interesse** — mit Ausnahme: entfällt bei menschlicher Überprüfung / redaktioneller Kontrolle mit benannter verantwortlicher Person.

**Nicht erfasst:** rein interne Inhalte, offensichtlich unrealistische oder comicartige Darstellungen, bloßes Teilen/Zitieren fremder KI-Inhalte, sowie KI-Systeme mit reiner Hilfsfunktion für einfache Bearbeitung (z. B. Rechtschreibprüfung) ohne wesentliche Änderung der Eingabedaten. Bei künstlerischen, satirischen oder fiktionalen Werken ist die Pflicht auf eine Offenlegung „in geeigneter Weise“ reduziert, die den Genuss des Werks nicht beeinträchtigen darf.

### Anbieter (Art. 50 Abs. 1, 2)
Maschinenlesbare Markierung synthetischer Outputs. Für diese Extension **nachrangig** — sie unterstützt sie (C2PA/XMP), fokussiert aber auf die Betreiberpflicht.

### Stichtagslogik — zwingend im Datenmodell abzubilden
Inhalte, die **vor dem 02.08.2026 erzeugt** wurden, müssen **nicht nachträglich** gekennzeichnet werden. Maßgeblich ist bei Bild/Audio/Video der Zeitpunkt der **Erzeugung** (nicht der Veröffentlichung), bei Texten zusätzlich der Zeitpunkt der Veröffentlichung. Die Extension muss daher ein Erzeugungsdatum führen und bei Datum < 2026-08-02 automatisch `pre_cutoff` als Ausnahmegrund vorschlagen.

### EU-Icons
Die Kommission stellt seit 10.06.2026 drei offizielle Icons bereit (SVG + PNG, vier Farbvarianten: schwarz, weiß, beide zusätzlich mit 50 % Transparenz), frei nutzbar ohne Attributionspflicht:

| Variante | Bedeutung |
|---|---|
| `basic` — „AI“ | KI war beteiligt; Art der Beteiligung nicht näher spezifiziert. Sinnvoll mit Textlabel oder zweiter Ebene kombiniert. |
| `generated` — „AI GENERATED“ | Vollständig KI-erzeugt, ohne menschliche Beteiligung außer dem Prompting. |
| `modified` — „AI MODIFIED“ | Mischform in beide Richtungen. |

**Die Icon-Dateien liegen nicht im Repository und dürfen nicht generiert oder nachgezeichnet werden.** Sie werden vom Betreiber manuell heruntergeladen und nach `Resources/Public/Icons/Eu/` gelegt. Erwartetes Namensschema:

```
Resources/Public/Icons/Eu/
├── ai-basic-black.svg          ai-basic-white.svg
├── ai-basic-black-50.svg       ai-basic-white-50.svg
├── ai-generated-black.svg      ai-generated-white.svg
├── ai-generated-black-50.svg   ai-generated-white-50.svg
├── ai-modified-black.svg       ai-modified-white.svg
└── ai-modified-black-50.svg    ai-modified-white-50.svg
```

Fehlen die Dateien, muss die Extension sauber degradieren: Textlabel statt Icon, plus Hinweis im Systemstatus-Report (`Classes/Report/`). **Kein Fatal Error, kein leeres Bild.**

Quelle für den Download: `https://digital-strategy.ec.europa.eu/de/policies/eu-icons-labelling-ai-generated-content`

### Absolute Grenzen — bitte streng einhalten
- **Kein Text, der Rechtskonformität verspricht.** Weder in Code-Kommentaren, noch in Labels, README oder Backend-Modul. Zulässig: „unterstützt bei der Umsetzung“. Unzulässig: „macht Ihre Website rechtssicher“, „garantiert Compliance“.
- **Die Extension trifft nie selbstständig die rechtliche Einordnung.** Automatisch erkannte Provenienzdaten werden immer nur als **Vorschlag** eingetragen (`status` bleibt bis zur menschlichen Bestätigung auf „Vorschlag“). Eine Aussage „dieser Inhalt ist KI-generiert“ ist eine rechtliche Behauptung und braucht eine menschliche Freigabe.
- **Keine Deepfake-Detektion an fremden Bildern.** Heuristische Bilddetektoren sind unzuverlässig; ein Falschpositiv erzeugt eine falsche Rechtsbehauptung.

---

## 2. Architektur

```
nt_aimark/
├── Classes/
│   ├── Domain/
│   │   ├── Enum/          AiStatus, IconVariant, ExemptReason, C2paState, TextStatus
│   │   ├── Model/         AiDeclaration, AuditEntry
│   │   └── Repository/    DeclarationRepository, AuditRepository
│   ├── Service/
│   │   ├── ProvenanceExtractorService
│   │   ├── C2paService
│   │   ├── XmpReaderService
│   │   ├── DisclosureRuleService
│   │   ├── LabelRenderService
│   │   ├── IconResolverService
│   │   ├── MetadataPreservationService
│   │   └── AuditService
│   ├── EventListener/
│   │   ├── AfterFileAddedListener
│   │   ├── AfterFileReplacedListener
│   │   ├── AfterFileProcessingListener
│   │   └── AiContentGeneratedListener
│   ├── Middleware/          (Andockpunkt fuer nt-aimark-pro)
│   ├── Resource/Rendering/  MarkedImageRenderer
│   ├── ViewHelpers/         AiLabelViewHelper, AiFigureViewHelper, HasLabelViewHelper
│   ├── Backend/
│   │   ├── Controller/      TransparencyModuleController
│   │   └── FormEngine/      LabelPreviewElement
│   ├── Command/             ScanCommand, ReportCommand, VerifyCommand
│   └── Report/              SystemStatusCheck
├── Configuration/
│   ├── TCA/Overrides/       sys_file_metadata.php, pages.php, tt_content.php
│   ├── Backend/Modules.php
│   ├── Sets/AiMark/         config.yaml, settings.definitions.yaml, setup.typoscript
│   ├── Services.yaml
│   └── Icons.php
├── Resources/
│   ├── Public/Icons/Eu/     (manuell befüllt, s. o.)
│   ├── Public/Css/aimark.css
│   ├── Public/JavaScript/aimark-details.js
│   └── Private/{Language,Templates,Partials,Layouts}/
├── Tests/{Unit,Functional,Acceptance}/
├── Build/                   phpstan.neon, php-cs-fixer.php, *Tests.xml
├── Documentation/
├── ext_emconf.php · ext_localconf.php · ext_tables.sql
└── composer.json
```

**Grundprinzipien**

- Ein Codestand für v13.4 und v14. Keine Versionsweichen im Klassendesign; wo nötig, kleine Kompatibilitäts-Helper.
- **Modulregistrierung:** In TYPO3 v14 wurde das Parent-Modul `web` in `content` umbenannt (Feature #107628). Das Parent-Modul muss zur Laufzeit ermittelt werden, statt es hart zu setzen. `nt_supporttimes` und `nt_ai` enthalten die gleiche Logik — dort nachsehen und konsistent übernehmen.
- Konfiguration über **Site Sets** (`Configuration/Sets/AiMark/`), nicht über klassisches statisches TypoScript-Include.
- Alle Services über Symfony DI in `Services.yaml`, `autowire: true`, `autoconfigure: true`, `public: false` — Ausnahmen nur, wo TYPO3 es erzwingt.
- Strict types in jeder Datei. Readonly-Klassen und Enums nutzen, wo sinnvoll.
- **Kein Fatal Error bei fehlenden optionalen Abhängigkeiten** (c2patool, exiftool, EU-Icons, nt_ai). Immer degradieren und im Systemstatus melden.

---

## 2a. Zwei Pakete — was das für den Code bedeutet

Beide Pakete sind GPL-2.0-or-later. Der Unterschied liegt ausschließlich im Vertriebsweg, nicht in einer technischen Sperre.

| Paket | Vertrieb | Inhalt |
|---|---|---|
| `netthinks/nt-aimark` — **dieses Repo** | TER, Packagist, öffentlich | Vollständiger Kernfunktionsumfang, eigenständig nutzbar |
| `netthinks/nt-aimark-pro` — später, eigenes Repo | privates Satis, tokengeschützt | Middleware-Fallback, Icon-Einbrennung, Audit-Ansicht und Export, Transparenzerklärung, Anbindung an einen gehosteten Dienst |

### Verbindliche Regeln

1. **Baue keinerlei Lizenz-, Aktivierungs- oder Domainprüfung ein.** Kein Lizenzschlüsselfeld, keine Freischaltlogik, kein „Pro-Feature gesperrt“-Hinweis, kein Phone-Home. Auch nicht vorbereitend, auch nicht auskommentiert. Ein Runtime-Check wäre bei GPL-Code rechtlich angreifbar — und für ein Produkt, das mit Rechtskonformität wirbt, wäre das die falsche Grundlage.
2. **Das Kernpaket muss ohne das Pro-Paket vollständig sinnvoll sein.** Keine Funktion darf halbfertig wirken oder auf ein „Upgrade“ verweisen. Wer nur das freie Paket installiert, bekommt ein rundes Produkt.
3. **Erweiterungspunkte statt Platzhalter.** Wo in diesem Dokument steht „vorbereiten, nicht implementieren“, ist damit gemeint: ein sauberes Interface, ein Event oder ein DI-Service-Alias, über den sich ein zweites Paket einklinken kann — **ohne** Patch, ohne Vererbung von Klassen, die dafür nicht gedacht sind, ohne Reflection.
4. **Erweiterungspunkte sind öffentliche API.** Alles, woran `nt-aimark-pro` andockt, wird mit `@api` annotiert, in `Documentation/Integration.md` dokumentiert und darf innerhalb einer Major-Version nicht brechen. Konkret betrifft das mindestens:
   - `IconCompositorInterface` — Einbrennen des Icons in die Prozessdatei
   - `LabelDecisionModifierInterface` — nachgelagerte Beeinflussung der Kennzeichnungsentscheidung
   - `AuditService` — muss `public: true` sein, damit ein zweites Paket schreiben kann
   - PSR-14-Events rund um Labelentscheidung und Statusänderung
   - Ein registrierbarer Punkt in der Middleware-Kette für den späteren HTML-Fallback
5. **Konfigurationsschlüssel des Pro-Pakets** liegen im eigenen Namensraum (`aimarkPro.*`) und tauchen im Kernpaket nicht auf.

Wenn im Verlauf der Umsetzung ein weiterer Erweiterungspunkt nötig erscheint: anlegen, `@api` annotieren, dokumentieren — lieber einer zu viel als ein späterer Patch am Kernpaket.

---

## 3. Datenmodell

### 3.1 `sys_file_metadata` — Erweiterung

Alle Felder mit Präfix `tx_ntaimark_`. In `ext_tables.sql` anlegen, in `Configuration/TCA/Overrides/sys_file_metadata.php` konfigurieren.

| Feld | SQL | Beschreibung |
|---|---|---|
| `tx_ntaimark_status` | `tinyint(1) unsigned DEFAULT 0` | `0` ungeprüft · `1` kein KI-Einsatz · `2` KI-generiert · `3` KI-bearbeitet · `4` Herkunft unbekannt · `5` Vorschlag aus Auto-Erkennung (noch unbestätigt) |
| `tx_ntaimark_disclosure` | `tinyint(1) unsigned DEFAULT 0` | `0` automatisch nach Regelwerk · `1` kennzeichnen · `2` nicht kennzeichnungspflichtig |
| `tx_ntaimark_exempt_reason` | `varchar(32) DEFAULT ''` | `pre_cutoff`, `not_realistic`, `artistic`, `satire`, `fiction`, `internal`, `minor_assist`, `other` |
| `tx_ntaimark_icon` | `varchar(16) DEFAULT ''` | `basic`, `generated`, `modified`, `none` |
| `tx_ntaimark_label_text` | `varchar(255) DEFAULT ''` | Freitext, z. B. „Stimmen erzeugt mit“ |
| `tx_ntaimark_system` | `varchar(128) DEFAULT ''` | z. B. „DALL·E 3“ |
| `tx_ntaimark_vendor` | `varchar(128) DEFAULT ''` | z. B. „OpenAI“ |
| `tx_ntaimark_prompt` | `text` | Optional, interne Nachweisführung |
| `tx_ntaimark_created_at` | `int(11) unsigned DEFAULT 0` | **Erzeugungszeitpunkt** — steuert die Stichtagslogik |
| `tx_ntaimark_reviewer` | `int(11) unsigned DEFAULT 0` | `be_users.uid` |
| `tx_ntaimark_reviewed_at` | `int(11) unsigned DEFAULT 0` | |
| `tx_ntaimark_c2pa_state` | `tinyint(1) unsigned DEFAULT 0` | `0` keine · `1` gültig · `2` ungültig/gebrochen · `3` nicht prüfbar |
| `tx_ntaimark_c2pa_manifest` | `text` | JSON, auf 64 kB gekappt |
| `tx_ntaimark_source_type` | `varchar(255) DEFAULT ''` | IPTC DigitalSourceType URI |
| `tx_ntaimark_notes` | `text` | Interne Notiz |

**TCA:** eigener Reiter `KI-Transparenz` in `sys_file_metadata`. Die Felder `exempt_reason`, `label_text`, `system`, `vendor`, `prompt` über `displayCond` nur einblenden, wenn sie relevant sind — Redakteure sollen nicht mit 15 Feldern konfrontiert werden, wenn drei genügen.

### 3.2 Texte — `pages`, `tt_content`, konfigurierbare Zusatztabellen

| Feld | SQL | Beschreibung |
|---|---|---|
| `tx_ntaimark_text_status` | `tinyint(1) unsigned DEFAULT 0` | `0` kein KI-Einsatz · `1` KI-Entwurf, überarbeitet · `2` KI-generiert |
| `tx_ntaimark_public_interest` | `tinyint(1) unsigned DEFAULT 0` | Angelegenheit von öffentlichem Interesse |
| `tx_ntaimark_editorial_control` | `tinyint(1) unsigned DEFAULT 0` | Menschliche Prüfung erfolgt → Ausnahmetatbestand |
| `tx_ntaimark_responsible` | `varchar(255) DEFAULT ''` | Redaktionsverantwortliche Person |

Weitere Tabellen (z. B. `tx_news_domain_model_news`) müssen über die Extension-Konfiguration nachrüstbar sein: eine kommaseparierte Tabellenliste, die in `ext_localconf.php` ausgewertet wird und die gleichen Felder ergänzt.

### 3.3 Audit-Tabelle `tx_ntaimark_audit`

Append-only. Kein Update, kein Delete durch die Anwendung.

```sql
CREATE TABLE tx_ntaimark_audit (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT 0 NOT NULL,
    tstamp int(11) unsigned DEFAULT 0 NOT NULL,
    table_name varchar(64) DEFAULT '' NOT NULL,
    record_uid int(11) unsigned DEFAULT 0 NOT NULL,
    be_user int(11) unsigned DEFAULT 0 NOT NULL,
    be_user_name varchar(255) DEFAULT '' NOT NULL,
    action varchar(32) DEFAULT '' NOT NULL,
    field_name varchar(64) DEFAULT '' NOT NULL,
    old_value text,
    new_value text,
    source varchar(32) DEFAULT '' NOT NULL,
    PRIMARY KEY (uid),
    KEY record (table_name, record_uid),
    KEY tstamp (tstamp)
);
```

`source` ∈ `manual`, `auto_detect`, `nt_ai`, `nt_lingua`, `cli`, `import`. `be_user_name` wird denormalisiert mitgeschrieben, damit der Nachweis auch nach Löschung des Backend-Users lesbar bleibt.

---

## 4. Regelwerk: `DisclosureRuleService`

Die zentrale fachliche Klasse. Sie beantwortet: *Muss dieses Asset gekennzeichnet werden, und wie?*

Signatur (Richtwert):

```php
public function resolve(AiDeclaration $declaration, SiteSettings $settings): LabelDecision;
```

`LabelDecision` ist ein readonly Value Object mit: `shouldLabel: bool`, `iconVariant: IconVariant`, `labelText: string`, `reason: string` (maschinenlesbarer Begründungscode für das Audit), `detailPayload: array`.

Entscheidungsreihenfolge — **genau so implementieren**:

1. `disclosure === 2` (manuell ausgenommen) → kein Label, `reason = manual_exempt`
2. `created_at > 0 && created_at < 2026-08-02` → kein Label, `reason = pre_cutoff`
3. `status ∈ {0, 5}` (ungeprüft oder unbestätigter Vorschlag) → kein Label im Frontend, aber im Backend als offener Vorgang zählen, `reason = unreviewed`. **Nie ein Label aus einem unbestätigten Vorschlag rendern.**
4. `status === 1` (kein KI-Einsatz) → kein Label, `reason = no_ai`
5. `disclosure === 1` (manuell erzwungen) → Label, `reason = manual_forced`
6. `status ∈ {2, 3}` → Label, Icon aus `icon`, ersatzweise `generated` bzw. `modified` je Status, `reason = rule_default`
7. `status === 4` (Herkunft unbekannt) → konfigurierbar per Site-Setting; Standard: kein Label, `reason = unknown_origin`

Diese Reihenfolge ist vollständig durch Unit-Tests abzudecken — eine Test-Matrix über alle Kombinationen von `status` × `disclosure` × Stichtag.

---

## 5. Automatische Erkennung

`ProvenanceExtractorService`, ausgelöst über `AfterFileAddedEvent` und `AfterFileReplacedEvent`. Leseprioritäten:

1. **C2PA-Manifest** über `C2paService`. Aufruf des externen Binaries `c2patool` via `Symfony\Component\Process\Process`, Pfad konfigurierbar, Timeout 15 s. Fehlt das Binary → `c2pa_state = 3` und weiter mit Stufe 2. **Niemals blockieren, niemals Exception nach außen durchreichen.**
2. **XMP** — Feld `Iptc4xmpExt:DigitalSourceType`. Relevante Werte:
   - `http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia` → Status-Vorschlag „KI-generiert“
   - `http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia` → „KI-bearbeitet“
   - `http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia` → „KI-generiert“ (nicht-trainiert erzeugt)
3. **EXIF-Heuristik** — Felder `Software`, `Credit`, `ImageDescription` gegen eine konfigurierbare Signaturliste (Midjourney, DALL·E, Firefly, Gemini, Stable Diffusion). Ergebnis mit niedrigster Konfidenz.

**Ergebnisbehandlung:** Immer `status = 5` (Vorschlag) setzen, nie einen bestätigten Status. Ein Auditeintrag mit `source = auto_detect` wird geschrieben. Findet sich ein Erzeugungsdatum vor dem 02.08.2026, zusätzlich `exempt_reason = pre_cutoff` vorschlagen.

---

## 6. Metadaten-Erhalt bei der Bildverarbeitung ⚠️

**Dies ist der technisch riskanteste Teil. Vor der Implementierung experimentell verifizieren und das Ergebnis in `Documentation/Metadata.md` festhalten.**

Problem: TYPO3 entfernt bei der Bildverarbeitung standardmäßig Profile (`processor_stripColorProfileCommand`, typischerweise `+profile '*'`). Damit verlieren skalierte Varianten XMP und C2PA — die maschinenlesbare Markierung überlebt den ersten `f:image`-Aufruf nicht.

Vorgehen:

1. **Erst messen.** Ein kleines Skript unter `Build/experiments/` bauen, das ein Testbild mit XMP + C2PA durch die TYPO3-Bildverarbeitung schickt und protokolliert, was übrig bleibt — getrennt für ImageMagick und GraphicsMagick, mit und ohne Strip-Konfiguration. Erst danach implementieren.
2. `MetadataPreservationService`, angebunden über einen Listener auf `AfterFileProcessingEvent`: schreibt die XMP-Kernfelder (`DigitalSourceType`, Erzeugungsdatum, System) in die Prozessdatei zurück.
3. Eine C2PA-Signatur ist nach Reskalierung technisch gebrochen und darf **nicht** unverändert kopiert werden — das wäre eine falsche kryptografische Behauptung. Entweder ein neues Manifest über die abgeleitete Datei erzeugen (erfordert Signaturzertifikat, in v1.0 **nicht** enthalten) oder das Feld leer lassen und `c2pa_state = 2` an der Prozessdatei vermerken.
4. `SystemStatusCheck` warnt im Backend, wenn die GFX-Konfiguration Metadaten zerstört und die Reparatur nicht aktiv ist.

---

## 7. Frontend-Ausgabe

Vier Wege, bewusst redundant. **In v1.0 sind Weg 1 und 2 Pflicht, Weg 3 und 4 sind als Schnittstelle vorzubereiten, aber noch nicht auszubauen.**

### 7.1 ViewHelper
```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>

<nt:aiLabel file="{file}" position="bottom-right" size="medium" />
<f:if condition="{nt:hasLabel(file: file)}">…</f:if>
```

### 7.2 FileRenderer
`MarkedImageRenderer implements FileRendererInterface`, Priorität knapp über dem Core-Renderer. Delegiert an den Core und umschließt das Ergebnis. Per Site-Setting abschaltbar, damit Projekte mit eigenen Templates nicht doppelt labeln.

### 7.3 Middleware-Fallback *(nur Erweiterungspunkt)*
Post-Processing des HTML-Outputs, das `<img>`-Tags über den Prozessdatei-Pfad auf markierte Assets zurückführt. Rettet Altprojekte mit gewachsenen Templates ohne Refactoring.

**Hier nur den Andockpunkt schaffen:** eine benannte Position in der Middleware-Kette plus ein Service, der zu einem Prozessdatei-Pfad die zugehörige `AiDeclaration` auflöst. Die Implementierung selbst kommt aus `nt-aimark-pro`.

### 7.4 Icon-Einbrennung in die Bilddatei *(nur Erweiterungspunkt)*
Das EU-Icon wird serverseitig per ImageMagick in die Prozessdatei komponiert. Überlebt Rechtsklick/Speichern, Hotlinking und Weitergabe über soziale Netze.

**Hier nur:** `IconCompositorInterface` definieren, mit `@api` annotieren und eine No-Op-Standardimplementierung registrieren, die unverändert durchreicht. Kein Hinweis im Backend, dass hier etwas fehlt.

### 7.5 Markup

```html
<figure class="nt-aimark" data-ai-status="generated">
    <img src="…" alt="…">
    <span class="nt-aimark__badge nt-aimark__badge--bottom-right"
          role="img"
          aria-label="Mit künstlicher Intelligenz erzeugtes Bild">
        <svg …><!-- EU-Icon, inline eingebunden --></svg>
    </span>
    <button type="button"
            class="nt-aimark__toggle"
            aria-expanded="false"
            aria-controls="aimark-detail-42">
        Details zur KI-Nutzung
    </button>
    <div id="aimark-detail-42" class="nt-aimark__detail" hidden>
        <dl>
            <dt>Erzeugt mit</dt><dd>DALL·E 3 (OpenAI)</dd>
            <dt>Erzeugt am</dt><dd>14.09.2026</dd>
        </dl>
    </div>
</figure>
```

Das Icon wird **inline als SVG** eingebunden, nicht als `<img>` — nur so lassen sich Farbe und Kontrast per CSS steuern.

---

## 8. Barrierefreiheit — blockierendes Qualitätskriterium

Nicht optional. Verstöße lassen die CI fehlschlagen.

- Badge als `role="img"` mit `aria-label`, niemals als reines Dekorbild ohne Textalternative
- Kontrast: `IconResolverService` wählt zwischen schwarzer und weißer Variante anhand der mittleren Helligkeit des Bildausschnitts hinter dem Badge; Fallback ist eine Variante mit Hintergrundfläche und garantiertem Kontrast ≥ 4.5:1
- Detail-Panel vollständig per Tastatur bedienbar, korrektes `aria-expanded`, Fokus bleibt beim Toggle
- Information nie allein über Farbe oder Position transportiert
- Alle Texte über XLIFF lokalisierbar, mehrsprachig über die Site-Konfiguration
- Kein Layout-Shift: das Badge darf die Bildposition nicht verschieben
- `Tests/Acceptance/` mit Playwright + axe-core, WCAG 2.1 AA, als **blockierendes** CI-Gate

---

## 9. Backend-Modul „KI-Transparenz“

Registrierung über `Configuration/Backend/Modules.php`, Parent-Modul zur Laufzeit ermittelt (s. Abschnitt 2).

**v1.0-Umfang:**
- Übersicht: Anteil geprüfter Assets je Storage, Zahl offener Vorgänge, Assets mit gebrochener C2PA-Signatur
- Arbeitsliste: filterbar nach Status, Storage, Zeitraum; Direktsprung in die Metadatenbearbeitung
- Massenbearbeitung: Status für mehrere Assets gleichzeitig setzen — mit Auditeintrag je Asset
- Hinweisbereich mit dem Disclaimer aus Abschnitt 1 („unterstützt bei der Umsetzung, ersetzt keine rechtliche Prüfung“)

**Nicht hier bauen, sondern als Erweiterungspunkt anlegen:** Audit-Log-Ansicht, CSV-/PDF-Export, Veröffentlichungssperre. Der `AuditService` schreibt im Kernpaket bereits vollständig — nur die Auswertungsansicht kommt aus `nt-aimark-pro`. Das Modul muss ohne diese Ansicht vollständig wirken; kein ausgegrauter Menüpunkt, kein Upgrade-Hinweis.

---

## 10. Schnittstellen zu `nt_ai` und `nt_lingua`

Beide Extensions gehören zur gleichen Produktfamilie. **Keine harte Abhängigkeit in eine der beiden Richtungen** — dasselbe Entkopplungsprinzip wie zwischen `nt_lingua` und `nt_ai`.

`nt_aimark` definiert und dokumentiert einen Event, auf den es hört:

```php
namespace NetThinks\NtAimark\Event;

final readonly class AiContentGeneratedEvent
{
    public function __construct(
        public string $tableName,
        public int $recordUid,
        public string $aiSystem,
        public string $aiVendor,
        public string $contentKind,   // 'image' | 'text' | 'audio' | 'video' | 'alt_text'
        public bool $fullyGenerated,  // true => generated, false => modified
        public ?string $prompt = null,
        public ?int $generatedAt = null,
    ) {}
}
```

`AiContentGeneratedListener` übernimmt die Daten und setzt `status = 5` (Vorschlag) plus Auditeintrag mit `source = nt_ai` bzw. `nt_lingua`. Der Event ist mit `@api` zu kennzeichnen und in `Documentation/Integration.md` zu dokumentieren, damit `nt_ai` und `nt_lingua` ihn später dispatchen können.

---

## 11. CLI

| Befehl | Zweck |
|---|---|
| `aimark:scan [--storage=1] [--force]` | Storages erneut auf Provenienzdaten prüfen |
| `aimark:report [--email=…]` | Report ungeprüfter Assets |
| `aimark:verify` | C2PA-Signaturen markierter Assets revalidieren |

Alle mit `--dry-run`. Ausgabe über `SymfonyStyle`, sauberer Exit-Code.

---

## 12. Tests

- **Unit:** `DisclosureRuleService` mit vollständiger Entscheidungsmatrix (Pflicht), `ProvenanceExtractorService` mit Fixtures, `IconResolverService` inkl. Kontrastberechnung, alle Enums
- **Functional:** TCA-Registrierung, Migration, Auditschreibung, CLI-Befehle, FileRenderer-Ausgabe
- **Acceptance:** axe-core gegen eine Beispielseite mit gelabelten Bildern
- **Fixtures:** Testbilder mit und ohne XMP `DigitalSourceType` unter `Tests/Fixtures/` — klein halten, unter 50 kB
- Zielabdeckung `Classes/Service/`: ≥ 80 %. PHPStan Level 8 muss ohne Baseline durchlaufen.

---

## 13. Reihenfolge der Umsetzung

Bitte in dieser Reihenfolge arbeiten und nach jedem Schritt committen.

1. **Grundgerüst** — `ext_localconf.php`, `ext_tables.sql`, `Services.yaml`, Enums, `AiDeclaration`. CI muss grün sein.
2. **Datenmodell & TCA** — `sys_file_metadata`-Erweiterung, Reiter, `displayCond`-Logik. Manuelle Pflege im Backend muss funktionieren.
3. **`DisclosureRuleService` + vollständige Unit-Tests.** Das fachliche Herzstück, vor allem anderen abgesichert.
4. **Frontend-Ausgabe** — ViewHelper, `LabelRenderService`, `IconResolverService`, CSS, JS, Templates. Inklusive Degradation ohne Icon-Dateien.
5. **Barrierefreiheit** — Acceptance-Tests, Kontrastlogik, Tastaturbedienung.
6. **FileRenderer** + Site-Settings zum Abschalten.
7. **Automatische Erkennung** — `XmpReaderService`, EXIF-Heuristik, dann `C2paService` mit Degradation.
8. **Metadaten-Erhalt** — erst das Experiment aus Abschnitt 6, dann implementieren.
9. **Texte** — `pages`/`tt_content`-Felder, konfigurierbare Zusatztabellen, Textlabel-Ausgabe.
10. **Backend-Modul** + `SystemStatusCheck`.
11. **CLI-Befehle.**
12. **Audit-Service** durchgängig verdrahten.
13. **Event- und Erweiterungsschnittstellen** für `nt_ai`, `nt_lingua` und `nt-aimark-pro` — alle mit `@api`, dokumentiert in `Documentation/Integration.md`.
14. **Dokumentation** — README (deutsch), `Documentation/` mit Installation, Konfiguration, Redaktionsleitfaden, Metadata-Befund, Integration.

---

## 14. Offene Punkte, die eine Rückfrage brauchen

Bitte nicht raten, sondern nachfragen bzw. verifizieren:

1. **PHP-Mindestversion von TYPO3 v14** — die CI-Matrix enthält dazu eine Annahme (v14 ohne PHP 8.2). Vor dem ersten Release gegen die tatsächliche Anforderung prüfen und die Matrix korrigieren.
2. **`LICENSE`** enthält aktuell nur einen Platzhalter; der GPL-2.0-Volltext ist vor dem ersten öffentlichen Push einzufügen.
3. **EU-Icons** liegen noch nicht im Repo (s. Abschnitt 1). Bis dahin nur Textlabel testen.
4. **Verfügbarkeit von `c2patool`** in der Zielumgebung — falls unklar, `C2paService` zunächst nur mit Interface und Null-Implementierung bauen.
5. **Namensraum des Pro-Pakets** — `netthinks/nt-aimark-pro` mit Extension-Key `nt_aimark_pro` ist die Annahme. Falls der Key im TER nicht verfügbar ist, vor dem Anlegen der Erweiterungspunkte klären.
6. Wenn eine Anforderung in diesem Dokument mit dem TYPO3-Core kollidiert, **halte an und melde es**, statt eine eigene Lösung zu erfinden.
