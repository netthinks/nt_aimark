# Konfiguration

Alle Einstellungen laufen über das Site Set `AI Mark`. Sie sind je Site
setzbar — im Backend unter *Websites → Einrichtung → Einstellungen* oder in
`config/sites/<identifier>/settings.yaml`.

## Einstellungen

| Schlüssel | Typ | Standard | Bedeutung |
|---|---|---|---|
| `ntAimark.labelUnknownOrigin` | bool | `false` | Ob Dateien mit unbekannter Herkunft gekennzeichnet werden |
| `ntAimark.useFileRenderer` | bool | `true` | Audio und Video ohne Template-Anpassung kennzeichnen |
| `ntAimark.showDetails` | bool | `true` | Aufklappbare Detailebene unter dem Symbol |
| `ntAimark.badgePosition` | string | `bottom-right` | `top-left`, `top-right`, `bottom-left`, `bottom-right` |
| `ntAimark.badgeSize` | string | `medium` | `small`, `medium`, `large` |

### `labelUnknownOrigin`

Standardmäßig aus, und das aus einem sachlichen Grund: „Herkunft unbekannt"
ist eine andere Aussage als „KI war beteiligt". Ein Label würde die zweite
behaupten. Schalten Sie die Einstellung nur ein, wenn Sie diese Aussage für
Ihren Bestand bewusst treffen wollen.

Beispiel `settings.yaml`:

```yaml
ntAimark:
  labelUnknownOrigin: false
  useFileRenderer: true
  showDetails: true
  badgePosition: bottom-right
  badgeSize: medium
```

### `useFileRenderer`

Kennzeichnet Audio- und Videoausgaben automatisch, ohne dass Templates
angepasst werden müssen. Ausschalten, wenn die Kennzeichnung dort bereits
über die ViewHelper gesetzt wird — sonst erscheint sie doppelt.

**Bilder deckt der FileRenderer bewusst nicht ab.** TYPO3 liefert für Bilder
keinen FileRenderer; `f:media` fällt auf eine eigene, private Ausgabe
zurück. Ein Bild-Renderer müsste diese Ausgabe nachbauen — samt
Crop-Varianten, Fokusbereich, `loading`, `decoding`, Alternativtext — und
diese Kopie über jedes Core-Release hinweg nachziehen. Für Bilder sind
deshalb die ViewHelper der vorgesehene Weg.

## ViewHelper-Argumente

Position, Größe und Detailebene lassen sich pro Aufruf übersteuern:

```html
<nt:aiLabel file="{file}" position="top-left" size="small" showDetails="false" />
```

| ViewHelper | Zweck |
|---|---|
| `nt:aiFigure` | Umschließt Bildmarkup mit `<figure>` und Kennzeichnung |
| `nt:aiLabel` | Rendert nur die Kennzeichnung |
| `nt:hasLabel` | Liefert `true`/`false` für eigene Fallunterscheidungen |
| `nt:textNotice` | Kennzeichnungshinweis für einen Textdatensatz |

### Texte kennzeichnen

```html
<nt:textNotice record="{data}" table="tt_content" />
```

Die Felder liegen von Haus aus auf `pages` und `tt_content`. Weitere Tabellen
lassen sich in den Extension-Einstellungen unter *Texte* nachrüsten,
kommagetrennt:

```
tx_news_domain_model_news, tx_blog_domain_model_post
```

Nach einer Änderung das Datenbankschema aktualisieren
(`vendor/bin/typo3 extension:setup`). Nur plausible Tabellennamen werden
übernommen; alles andere wird verworfen, statt in DDL oder TCA zu landen.

## Markup überschreiben

Die Ausgabe entsteht aus einem Fluid-Template
(`Resources/Private/Templates/Label/Badge.html`). Es lässt sich im eigenen
Sitepackage überschreiben, ohne PHP anzufassen.

Erzeugtes Markup:

```html
<figure class="nt-aimark" data-ai-status="generated">
    <img src="…" alt="…">
    <span class="nt-aimark__badge nt-aimark__badge--bottom-right nt-aimark__badge--medium nt-aimark__badge--generated"
          data-ai-variant="generated">
        <span class="nt-aimark__badge-icon" role="img" aria-label="Mit künstlicher Intelligenz erzeugtes Bild">
            <svg aria-hidden="true" focusable="false" class="nt-aimark__icon">…</svg>
        </span>
    </span>
    <button type="button" class="nt-aimark__toggle" aria-expanded="false" aria-controls="aimark-detail-42-1">
        Details zur KI-Nutzung
    </button>
    <div id="aimark-detail-42-1" class="nt-aimark__detail" hidden>
        <dl class="nt-aimark__detail-list">
            <dt>Erzeugt mit</dt><dd>DALL·E 3 (OpenAI)</dd>
            <dt>Erzeugt am</dt><dd>03.08.2026</dd>
        </dl>
    </div>
</figure>
```

Wenn Sie das Markup ersetzen, halten Sie diese vier Punkte ein — sie sind
keine Stilfrage:

- Das Symbol trägt eine Textalternative (`role="img"` mit `aria-label`), oder
  es steht sichtbarer Text daneben.
- Die Schaltfläche ist ein `<button>` mit korrektem `aria-expanded` und
  `aria-controls`; die `id` des Panels ist im Dokument eindeutig.
- Die Kennzeichnung verschiebt das Bild nicht.
- Der Kontrast des Symbols hängt nicht vom Bild darunter ab.

## Kontrast der Kennzeichnung

Die Extension misst den Bildbereich hinter dem Symbol und entscheidet daraus,
wie das Badge gezeichnet wird:

| Fall | Ergebnis | CSS-Klasse |
|---|---|---|
| Bereich messbar, gewählte Symbolfarbe erreicht dort ≥ 4,5:1 an **jedem** Messpunkt | Symbol ohne Plakette, schwarz oder weiß je nach Untergrund | `nt-aimark__badge--plain` |
| Bereich unruhig, Bild nicht lesbar, GD nicht verfügbar, Bild zu groß | Symbol auf deckender Plakette | `nt-aimark__badge--plate` |

Der Kontrast hängt damit nie vom Zufall ab: Die Plakette ist die Zusage, das
Weglassen die Ausnahme. Jeder Fehlerpfad führt zurück zur Plakette.

Gemessen wird das Viertel des Bildes, in dem das Symbol tatsächlich sitzt —
`badgePosition` steuert also mit, welcher Bereich betrachtet wird. Das
Ergebnis liegt im Cache `ntaimark`, verschlüsselt über den Inhalts-Hash der
Datei; ein ausgetauschtes Bild wird neu gemessen.

> Eine Eigenheit der Kontrastmathematik: Gegen eine **einfarbige** Fläche
> erreicht immer mindestens eine der beiden Symbolfarben 4,5:1, weil
> 4,5 × 4,5 kleiner als der maximale Kontrastumfang 21 ist. Die Plakette
> braucht es deshalb nur für unruhige Bereiche und für nicht messbare Bilder.

## Eigenes CSS

Die mitgelieferte Datei `EXT:nt_aimark/Resources/Public/Css/aimark.css` ist
bewusst schlank und ohne Farbschema Ihrer Website. Wollen Sie sie ersetzen,
binden Sie Ihre eigene Datei ein und überschreiben Sie die Klassen — der
AssetCollector-Eintrag heißt `ntAimark`.
