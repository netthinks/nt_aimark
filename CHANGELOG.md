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

### Bekannte Einschränkungen

- Die offiziellen EU-Icons liegen aus lizenz- und integritätsgründen nicht im
  Repository und müssen manuell ergänzt werden.
- Automatische Erkennung, Metadaten-Erhalt über die Bildverarbeitung,
  Backend-Modul und CLI-Befehle sind noch nicht enthalten.
- TYPO3 14.3 verwarnt `ext_emconf.php`. Die Datei bleibt bestehen, solange
  TYPO3 13.4 unterstützt wird.
