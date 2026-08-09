# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier festgehalten.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

## [0.1.0] – unveröffentlicht

Erster Entwicklungsstand. Noch nicht für den Produktiveinsatz geeignet.

### Hinzugefügt

- **Datenmodell** für die KI-Transparenz von Mediendateien: KI-Anteil,
  Kennzeichnungsmodus, Ausnahmegrund, Symbol, Beschriftung, eingesetztes
  System, Erzeugungsdatum, Prüfvermerk sowie Felder für erkannte
  Herkunftsdaten (C2PA, IPTC `DigitalSourceType`).
- **Stichtagslogik** zum 2. August 2026. Ein nicht gesetztes Erzeugungsdatum
  gilt ausdrücklich nicht als „vor Stichtag".
- **Backend-Reiter „KI-Transparenz"** in den Datei-Metadaten mit fünf
  Paletten. Felder werden nur eingeblendet, wenn sie zur Sache gehören.
- **Regelwerk** (`DisclosureRuleService`), das entscheidet, ob und wie
  gekennzeichnet wird. Ein unbestätigter Vorschlag aus der automatischen
  Erkennung führt nie zu einer Kennzeichnung im Frontend.
- **Barrierefreie Frontend-Ausgabe** über die ViewHelper `aiFigure`,
  `aiLabel` und `hasLabel`: EU-Symbol inline als SVG, aufklappbare
  Detailebene mit Tastaturbedienung, kein Layout-Shift.
- **Degradation ohne die EU-Icons**: Fehlen die Dateien, wird ein Textlabel
  ausgegeben statt eines leeren Elements.
- **Site Set `AI Mark`** mit Einstellungen für Symbolposition, Symbolgröße,
  Detailebene und den Umgang mit Dateien unbekannter Herkunft.
- **Audit-Tabelle** `tx_ntaimark_audit` (append-only) als Grundlage der
  Nachweisführung.
- **Kontrastlogik**: Der Bildbereich hinter dem Symbol wird gemessen; die
  deckende Plakette entfällt nur, wenn die gewählte Symbolfarbe dort an jedem
  Messpunkt 4,5:1 erreicht. Jeder Fehlerpfad führt zurück zur Plakette.
- **FileRenderer für Audio und Video**: kennzeichnet ohne Template-Anpassung,
  indem er die Ausgabe des Core-Renderers umschließt. Per Site-Einstellung
  `ntAimark.useFileRenderer` abschaltbar. Bilder deckt er bewusst nicht ab.
- **Barrierefreiheits-Gate** in der CI: Playwright mit axe-core gegen WCAG
  2.1 AA, dazu Prüfungen auf Tastaturbedienbarkeit, eindeutige
  `aria-controls`-Ziele und ausbleibenden Layout-Shift.

- **Automatische Erkennung** beim Upload und beim Ersetzen einer Datei, in
  drei Stufen: signiertes C2PA-Manifest, IPTC `DigitalSourceType` aus XMP,
  zuletzt eine Signaturliste über EXIF-Felder. Das Ergebnis ist immer nur ein
  **Vorschlag**; ein von einem Menschen bestätigter Status wird nie
  überschrieben. Liegt das Erzeugungsdatum vor dem Stichtag, wird zusätzlich
  `pre_cutoff` als Ausnahmegrund vorgeschlagen.
- **Protokollierung** der automatischen Erkennung in `tx_ntaimark_audit`
  (`source = auto_detect`), mit denormalisiertem Benutzernamen.
- **Extension-Einstellungen** für Pfad und Zeitlimit von `c2patool` sowie für
  zusätzliche EXIF-Signaturen.

- **Metadaten-Erhalt**: Das XMP-Paket wird nach der Bildverarbeitung in die
  abgeleitete JPEG-Datei zurückgeschrieben, ohne die Strip-Konfiguration von
  TYPO3 anzutasten. C2PA-Signaturen werden ausdrücklich **nicht** übertragen
  — sie sind nach dem Skalieren gebrochen, und eine mitkopierte Signatur
  würde die abgeleitete Datei als manipuliert ausweisen. Messergebnis siehe
  `Documentation/Metadata.md`.

- **Textkennzeichnung**: Felder auf `pages` und `tt_content`, weitere Tabellen
  über die Extension-Einstellungen nachrüstbar. Eigenes Regelwerk — die Pflicht
  greift nur bei Angelegenheiten von öffentlichem Interesse und entfällt bei
  redaktioneller Prüfung **mit** benannter verantwortlicher Person. Ein Häkchen
  ohne Namen hebt die Pflicht nicht auf. Ausgabe über `nt:textNotice` als Satz
  statt als Symbol.

- **Backend-Modul „KI-Transparenz"**: Übersicht je Speicher (geprüft, offen,
  gebrochene Signaturen), filterbare Arbeitsliste mit Direktsprung in die
  Metadatenbearbeitung, Massenbearbeitung mit einem Protokolleintrag je Datei,
  Systemstatus und der Hinweis, dass das Modul keine Rechtsberatung ersetzt.
- **SystemStatusCheck**: meldet fehlende EU-Symbole, fehlendes `c2patool`,
  eine Bildverarbeitung, die Metadaten zerstört, und eine fehlende
  PHP-Erweiterung `exif` — alles Fälle, in denen die Extension bewusst leise
  degradiert und der Betreiber es trotzdem wissen sollte.

- **CLI-Befehle** `aimark:scan`, `aimark:report` und `aimark:verify`, alle mit
  `--dry-run` und über den Scheduler planbar. `aimark:scan --force` frischt
  bestehende Vorschläge auf, rührt einen von einem Menschen bestätigten
  Datensatz aber nie an. `aimark:verify` prüft Signaturen erneut, weil eine
  beim Upload gültige Signatur später brechen kann.

- **Durchgängige Protokollierung**: Jede Änderung an den Transparenzfeldern
  landet im Protokoll — die redaktionelle Bearbeitung im Formular ebenso wie
  Schreibvorgänge anderer Extensions über die FAL-API, die Massenbearbeitung
  im Backend-Modul, die automatische Erkennung und die CLI-Befehle. Der
  vorherige Wert stammt aus dem Protokoll selbst; ein bereits erfasster
  Vorgang wird dadurch kein zweites Mal geschrieben.

- **Event-Schnittstelle** `AiContentGeneratedEvent` (`@api`): Wer KI-Inhalte
  erzeugt, meldet sie und die Kennzeichnung entsteht ohne redaktionellen
  Zusatzaufwand. Keine harte Abhängigkeit in eine der beiden Richtungen.
  Gemeldete Medien werden zum **Vorschlag**, nicht zur Feststellung; gemeldete
  Texte als Tatsache erfasst, weil die meldende Extension sie selbst
  geschrieben hat; ein gemeldeter **Alt-Text ändert den Status des Bildes
  nicht**, weil er nichts darüber aussagt, wie das Bild entstanden ist.

- **Dokumentation**: `Documentation/` nach TYPO3-Konvention und parallel dazu
  eine zweisprachige MkDocs-Site unter `docs/` (Material-Theme, i18n), nach
  demselben Muster wie nt_ai und nt_lingua.

- **Erweiterungspunkte** für ein späteres Zusatzpaket, alle `@api` und in
  `Documentation/Integration.md` dokumentiert: `LabelDecisionModifierInterface`,
  die Events `AfterLabelDecisionEvent` und `AfterStatusChangedEvent`,
  `IconCompositorInterface` und `LabelInjectorInterface` (beide mit
  Durchreiche-Standard), ein benannter Platz in der Middleware-Kette sowie der
  `ProcessedFileDeclarationResolver`. `AuditService` ist beschreibbar.
  **Keinerlei Lizenz-, Aktivierungs- oder Domainprüfung** — ein Test sichert
  das ab.

### Bekannte Einschränkungen

- Die offiziellen EU-Icons liegen aus lizenz- und integritätsgründen nicht im
  Repository und müssen manuell ergänzt werden.

- Der Metadaten-Erhalt deckt nur JPEG ab; PNG und WebP verlieren ihr
  XMP-Paket bei der Verarbeitung.
- Content Credentials überleben die Bildverarbeitung grundsätzlich nicht.
- Ohne `c2patool` entfällt die C2PA-Stufe der Erkennung; XMP und EXIF greifen
  weiterhin.
- TYPO3 14.3 verwarnt `ext_emconf.php`. Die Datei bleibt bestehen, solange
  TYPO3 13.4 unterstützt wird.
