# Konfiguration

Alle Einstellungen laufen über das Site Set `AI Mark`. Sie sind je Site
setzbar — im Backend unter *Websites → Einrichtung → Einstellungen* oder in
`config/sites/<identifier>/settings.yaml`.

## Einstellungen

| Schlüssel | Typ | Standard | Bedeutung |
|---|---|---|---|
| `ntAimark.labelUnknownOrigin` | bool | `false` | Ob Dateien mit unbekannter Herkunft gekennzeichnet werden |
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
  showDetails: true
  badgePosition: bottom-right
  badgeSize: medium
```

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

## Eigenes CSS

Die mitgelieferte Datei `EXT:nt_aimark/Resources/Public/Css/aimark.css` ist
bewusst schlank und ohne Farbschema Ihrer Website. Wollen Sie sie ersetzen,
binden Sie Ihre eigene Datei ein und überschreiben Sie die Klassen — der
AssetCollector-Eintrag heißt `ntAimark`.
