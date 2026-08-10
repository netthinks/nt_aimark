# Architektur

Diese Seite beschreibt, wie die Extension aufgebaut ist und wo eine
Entscheidung tatsächlich fällt. Sie richtet sich an alle, die den Ablauf
nachvollziehen oder daran andocken wollen — nicht nur an Entwickler.

## Der Grundgedanke

Die Extension trennt drei Dinge, die gern vermischt werden:

| Stufe | Frage | Was dort passiert |
|---|---|---|
| **1 Erfassung** | Was ist über eine Datei bekannt? | Metadatenfelder, automatische Erkennung, Meldungen anderer Extensions |
| **2 Entscheidung** | Was folgt daraus? | Eine einzige Klasse entscheidet, ob und wie gekennzeichnet wird |
| **3 Ausgabe** | Wie sieht das aus? | ViewHelper, Symbol, Detailebene |
| **4 Nachweis** | Was bleibt davon? | Jede Statusänderung im Protokoll, unabhängig vom Weg |

Diese vier Stufen sind auch die vier Kästen im folgenden Diagramm.

Diese Trennung ist der Grund, warum eine automatische Erkennung nie zu einer
Kennzeichnung führen kann, ohne dass ein Mensch zugestimmt hat: Erfassung und
Entscheidung sind verschiedene Schritte, und der Übergang zwischen ihnen ist
genau eine Statusänderung.

## Überblick

```mermaid
flowchart TB
    subgraph erfassung["1 · ERFASSUNG — was über eine Datei bekannt ist"]
        upload[Datei-Upload] --> extract[ProvenanceExtractorService]
        extract --> c2pa[C2paService<br/>Content Credentials]
        extract --> xmp[XmpReaderService<br/>IPTC DigitalSourceType]
        extract --> exif[ExifSignatureService<br/>Signaturliste]
        event[AiContentGeneratedEvent<br/>aus nt_ai / nt_lingua] --> listener[AiContentGeneratedListener]
        form[Reiter „KI-Transparenz&quot;<br/>im Backend] --> meta
        c2pa --> meta[(sys_file_metadata<br/>tx_ntaimark_*)]
        xmp --> meta
        exif --> meta
        listener --> meta
    end

    subgraph entscheidung["2 · ENTSCHEIDUNG — was daraus folgt"]
        decl[AiDeclaration<br/>Wertobjekt] --> rules{{DisclosureRuleService}}
        settings[Site Set<br/>Einstellungen] --> rules
        rules --> decision[LabelDecision]
    end

    subgraph ausgabe["3 · AUSGABE — wie es aussieht"]
        vh[ViewHelper<br/>aiFigure / aiLabel / hasLabel] --> render[LabelRenderService]
        icons[IconResolverService<br/>EU-Symbole] --> render
        contrast[BadgeContrastService<br/>Helligkeit des Bildes] --> render
        render --> out([Kennzeichnung im Frontend])
    end

    subgraph nachweis["4 · NACHWEIS — was davon bleibt"]
        audit[(tx_ntaimark_audit<br/>Protokoll)]
    end

    meta --> decl
    decision --> vh

    meta -.jede Änderung.-> audit
    decision -.-> audit

    classDef stufe fill:#f4f6fa,stroke:#9aa6bd,stroke-width:1px
    class erfassung,entscheidung,ausgabe,nachweis stufe
```

Gepunktete Linien sind Nachweisführung: Jede Statusänderung landet im
Protokoll, gleich über welchen Weg sie entstanden ist.

## Der Entscheidungsweg

Das Regelwerk arbeitet eine feste Reihenfolge ab. Die erste zutreffende Regel
gewinnt und hinterlässt einen maschinenlesbaren Begründungscode im Protokoll.

```mermaid
flowchart TD
    start([AiDeclaration]) --> r1{Manuell<br/>ausgenommen?}
    r1 -->|ja| no1[keine Kennzeichnung<br/>manual_exempt]
    r1 -->|nein| r2{Erzeugt vor<br/>02.08.2026?}
    r2 -->|ja| no2[keine Kennzeichnung<br/>pre_cutoff]
    r2 -->|nein| r3{Ungeprüft oder<br/>unbestätigter Vorschlag?}
    r3 -->|ja| no3[keine Kennzeichnung<br/>unreviewed<br/>zählt als offener Vorgang]
    r3 -->|nein| r4{Kein KI-Einsatz?}
    r4 -->|ja| no4[keine Kennzeichnung<br/>no_ai]
    r4 -->|nein| r5{Manuell<br/>erzwungen?}
    r5 -->|ja| yes1[Kennzeichnung<br/>manual_forced]
    r5 -->|nein| r6{KI-generiert oder<br/>KI-bearbeitet?}
    r6 -->|ja| yes2[Kennzeichnung<br/>rule_default]
    r6 -->|nein| r7{Herkunft<br/>unbekannt?}
    r7 -->|ja| cfg[nach Einstellung<br/>unknown_origin]
```

Zwei Punkte daran sind Absicht und keine Randnotiz:

**Regel 2 greift nur bei gesetztem Datum.** Ein leeres Erzeugungsdatum gilt
ausdrücklich **nicht** als „vor Stichtag". Sonst würde jeder unvollständige
Datensatz automatisch zur Ausnahme.

**Regel 3 steht vor allem Weiteren, was zu einer Kennzeichnung führen könnte.**
Ein Vorschlag aus der automatischen Erkennung erzeugt nie eine Kennzeichnung
im Frontend. Die Aussage „dieser Inhalt ist KI-erzeugt" ist eine rechtliche
Behauptung und braucht eine menschliche Freigabe.

## Wo die Daten liegen

```mermaid
erDiagram
    sys_file ||--o| sys_file_metadata : "hat Metadaten"
    sys_file_metadata {
        int tx_ntaimark_status "Einstufung, 0-5"
        int tx_ntaimark_disclosure "automatisch / erzwungen / ausgenommen"
        string tx_ntaimark_exempt_reason
        string tx_ntaimark_icon
        string tx_ntaimark_system
        string tx_ntaimark_vendor
        int tx_ntaimark_created_at "steuert die Stichtagsregel"
        int tx_ntaimark_reviewer
        int tx_ntaimark_c2pa_state
        string tx_ntaimark_source_type "IPTC DigitalSourceType"
    }
    pages ||--o{ tx_ntaimark_audit : "protokolliert"
    tt_content ||--o{ tx_ntaimark_audit : "protokolliert"
    sys_file_metadata ||--o{ tx_ntaimark_audit : "protokolliert"
    tx_ntaimark_audit {
        int tstamp
        string table_name
        int record_uid
        string be_user_name "denormalisiert"
        string action
        string field_name
        text old_value
        text new_value
        string source "manual / auto_detect / nt_ai / cli"
    }
```

Das Protokoll ist **append-only**: Die Anwendung schreibt nur hinzu, sie
ändert und löscht nichts. Der Benutzername wird mitgeschrieben statt nur
verwiesen, damit der Nachweis lesbar bleibt, wenn der Backend-Benutzer später
gelöscht wird.

Texte in `pages` und `tt_content` tragen eigene Felder
(`tx_ntaimark_text_status`, `tx_ntaimark_public_interest`,
`tx_ntaimark_editorial_control`, `tx_ntaimark_responsible`); weitere Tabellen
lassen sich in den Extension-Einstellungen nachrüsten.

## Zwei Wege, auf denen eine Änderung ins Protokoll kommt

Das ist die unauffälligste Stelle der Architektur und zugleich die, an der am
ehesten etwas verlorengeht.

```mermaid
flowchart LR
    api[Schreiben über die FAL-API<br/>Erkennung, CLI, Massenbearbeitung] --> ev[AfterFileMetaDataUpdatedEvent]
    form[Speichern im Metadaten-Formular] --> hook[DataHandler-Hook]
    ev --> rec[MetaDataAuditRecorder]
    hook --> rec
    rec --> audit[(tx_ntaimark_audit)]
```

Der PSR-14-Event feuert **nur** bei Schreibzugriffen über die FAL-API. Ein
Redakteur, der das Formular speichert, löst ihn nicht aus — TYPO3 v14 bietet
für Datensatzänderungen keinen entsprechenden Event. Deshalb der zusätzliche
DataHandler-Hook. Beide Wege münden in dieselbe Klasse, und der vorherige Wert
kommt aus dem Protokoll selbst; damit entsteht kein doppelter Eintrag, wenn
beide Wege denselben Vorgang sehen.

## Ausgabe im Frontend

```mermaid
sequenceDiagram
    participant T as Fluid-Template
    participant V as AiFigureViewHelper
    participant R as DisclosureRuleService
    participant L as LabelRenderService
    participant B as BadgeContrastService
    participant I as IconResolverService

    T->>V: nt:aiFigure mit Datei
    V->>R: Regelwerk anwenden
    R-->>V: LabelDecision
    alt keine Kennzeichnung nötig
        V-->>T: Bild unverändert
    else Kennzeichnung
        V->>L: renderBadge(Bildmarkup)
        L->>B: Helligkeit hinter dem Symbol
        B-->>L: schwarz oder weiß
        L->>I: Symbolvariante
        I-->>L: SVG, Farben als Attribute
        L-->>V: figure mit Rahmen, Symbol, Detailebene
        V-->>T: gekennzeichnetes Bild
    end
```

Drei Entscheidungen, die man dem Ergebnis nicht ansieht:

**Der ViewHelper darf bedingungslos gesetzt werden.** Trifft das Regelwerk
keine Kennzeichnung, gibt er das Bild unverändert zurück. Redaktionen müssen
also nicht wissen, welche Bilder betroffen sind.

**Das Symbol steht in einem eigenen Rahmen um das Bild**, nicht in der
gesamten `figure`. Sonst würde es unterhalb des Bildes landen — und die
Kontrastentscheidung, die anhand der Bildhelligkeit fällt, ginge ins Leere.

**Das SVG trägt seine Farben als Attribute**, nicht in einem `<style>`-Block.
Eine Content Security Policy mit Nonce für `style-src-elem` — in TYPO3 v14 der
Standard — verwirft Inline-Stylesheets kommentarlos; das offizielle Symbol
würde sonst als schwarze Fläche erscheinen.

Fehlen die EU-Symbole ganz, entsteht ein Textlabel statt eines leeren
Elements. Siehe [Installation](installation.md).

## Backend-Modul

![Das Modul „KI-Transparenz": zwei Ringe für Prüffortschritt und Verteilung, darunter Speicherübersicht, Systemstatus und die gefilterte Arbeitsliste](assets/backend-module.png)

```mermaid
flowchart LR
    repo[TransparencyRepository] --> kpi[Kennzahlen und Ringe]
    repo --> list[Arbeitsliste<br/>gefiltert, blätterbar]
    status[SystemStatusCheck] --> panel[Systemstatus]
    list --> bulk[Massenbearbeitung]
    bulk --> audit[(Protokoll)]
    bulk --> fal[FAL-Metadaten]
```

Das Repository nimmt automatisch erzeugte Formatvarianten
(`bild.jpg.webp`, `bild.jpg.avif`) aus allen Zählungen heraus — sie zeigen
denselben Inhalt wie das Original und würden Arbeitsliste wie Prüfquote
verzerren. Abschaltbar über die Einstellung `hideDerivedFormats`.

`SystemStatusCheck` prüft, was zur Laufzeit fehlen kann: EU-Symbole,
`c2patool`, die PHP-Erweiterung `exif` und eine GFX-Konfiguration, die
Metadaten zerstört.

## Erweiterungspunkte

Dieses Repository ist das freie Kernpaket. Zusatzfunktionen klinken sich über
definierte Punkte ein, ohne es zu patchen — und ohne jede Lizenz- oder
Freischaltlogik im Code.

```mermaid
flowchart TB
    core[Kernpaket nt-aimark]
    core --> m[LabelDecisionModifierInterface<br/>Entscheidung nachträglich beeinflussen]
    core --> e1[AfterLabelDecisionEvent<br/>beobachten]
    core --> e2[AfterStatusChangedEvent<br/>auf Statusänderungen reagieren]
    core --> ic[IconCompositorInterface<br/>Symbol ins Bild einbrennen]
    core --> mw[Middleware-Platz<br/>label-injection]
    core --> res[ProcessedFileDeclarationResolver<br/>Bildpfad zu Deklaration]
    core --> aud[AuditService public<br/>eigene Protokolleinträge]
```

Alle sind mit `@api` gekennzeichnet und in
[Integration](integration.md) im Einzelnen beschrieben.
