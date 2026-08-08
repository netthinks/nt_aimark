# Integration in andere Extensions

Wer KI-Inhalte erzeugt, kann das an `nt_aimark` melden. Die Kennzeichnung
entsteht dann ohne redaktionellen Zusatzaufwand — der Mensch bestätigt nur
noch.

Die Kopplung läuft ausschließlich über ein Event. **Keine harte Abhängigkeit
in eine der beiden Richtungen**: Ist `nt_aimark` nicht installiert, läuft das
Event ins Leere und die meldende Extension merkt nichts davon.

## Das Event

```php
use NetThinks\NtAimark\Event\AiContentGeneratedEvent;

$this->eventDispatcher->dispatch(new AiContentGeneratedEvent(
    tableName: 'sys_file',
    recordUid: $file->getUid(),
    aiSystem: 'DALL·E 3',
    aiVendor: 'OpenAI',
    contentKind: AiContentGeneratedEvent::KIND_IMAGE,
    fullyGenerated: true,
    prompt: $prompt,
    generatedAt: time(),
    source: 'nt_ai',
));
```

| Feld | Bedeutung |
|---|---|
| `tableName` | `sys_file`, `sys_file_metadata`, `pages`, `tt_content`, … |
| `recordUid` | uid in dieser Tabelle |
| `aiSystem` | Produktname, z. B. „DALL·E 3" |
| `aiVendor` | Anbieter, z. B. „OpenAI" |
| `contentKind` | `image`, `audio`, `video`, `text` oder `alt_text` |
| `fullyGenerated` | `true` = vollständig erzeugt, `false` = KI-unterstützt verändert |
| `prompt` | Optional, reine interne Nachweisführung — erscheint **nie** im Frontend |
| `generatedAt` | Optional, Erzeugungszeitpunkt (steuert die Stichtagsregel) |
| `source` | Optional, welche Extension meldet — landet im Protokoll |

Das Event ist mit `@api` gekennzeichnet und bleibt innerhalb einer
Hauptversion stabil.

## Was damit geschieht — und was bewusst nicht

Drei Entscheidungen, die nicht auf den ersten Blick offensichtlich sind:

### Medien bleiben ein Vorschlag

Auch eine Meldung aus erster Hand setzt den Status auf **„Vorschlag"**, nicht
auf „KI-generiert". Die öffentliche Aussage „dieses Bild ist KI-erzeugt"
bleibt eine menschliche Entscheidung — genau wie bei der automatischen
Erkennung. Das passende Symbol wird aus `fullyGenerated` schon vorbelegt,
damit die Bestätigung ein Klick ist.

Ein Datensatz, den ein Mensch bereits eingestuft hat, wird **nicht**
überschrieben.

### Texte werden als Tatsache erfasst

Beim Text ist die Lage anders: Die meldende Extension hat den Text selbst
geschrieben, das ist Wissen und keine Vermutung. `tx_ntaimark_text_status`
wird deshalb direkt gesetzt.

Eine Kennzeichnung entsteht daraus trotzdem nicht von allein — dafür braucht
es zusätzlich „Angelegenheit von öffentlichem Interesse", und das setzt nur
ein Mensch. Siehe [Regelwerk](rules.md).

### Ein Alt-Text ändert nichts am Bild

`KIND_ALT_TEXT` wird protokolliert, rührt den KI-Status des Bildes aber
**nicht** an. Ein Alt-Text ist eine Beschreibung *des* Bildes und sagt nichts
darüber aus, wie das Bild entstanden ist. Würde man das anders behandeln,
wäre jedes Bild mit KI-geschriebenem Alt-Text plötzlich „KI-generiert" — eine
Falschaussage über den Inhalt, und zwar in großer Zahl.

## Protokoll

Jede Meldung landet in `tx_ntaimark_audit` mit der Aktion `reported` und der
Quelle aus dem Feld `source` (`nt_ai`, `nt_lingua`, sonst `import`).

## Für nt_ai und nt_lingua

Sinnvolle Stellen zum Auslösen:

| Extension | Stelle | `contentKind` |
|---|---|---|
| `nt_ai` | Alt-Text-Generierung für ein FAL-Bild | `alt_text` |
| `nt_ai` | KI-erzeugter Text in einem Inhaltselement | `text` |
| `nt_lingua` | Übersetzung oder Einfache Sprache eines Textes | `text`, `fullyGenerated: false` |

Bei `nt_lingua` ist `fullyGenerated: false` der Regelfall: Eine Übersetzung
ist eine KI-Bearbeitung eines vorhandenen Textes, keine Neuschöpfung.
