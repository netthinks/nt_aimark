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
ein Mensch. Siehe [Regelwerk](Regelwerk.md).

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

---

# Erweiterungspunkte

Dieses Repository ist das **freie Kernpaket**. Es ist eigenständig vollständig
— keine gesperrte Funktion, kein Lizenzschlüssel, kein Upgrade-Hinweis
irgendwo in der Oberfläche. Zusatzfunktionen kommen als **eigenes
Composer-Paket** und klinken sich über die folgenden Punkte ein, ohne dieses
Paket zu patchen.

Alles hier Aufgeführte ist `@api` und bleibt innerhalb einer Hauptversion
stabil.

## Kennzeichnungsentscheidung nachträglich beeinflussen

```php
use NetThinks\NtAimark\Service\LabelDecisionModifierInterface;

final class MyModifier implements LabelDecisionModifierInterface
{
    public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision
    {
        return $decision;
    }
}
```

Registrieren mit dem Tag `nt_aimark.label_decision_modifier`; bei aktivem
`autoconfigure` genügt das Implementieren des Interfaces. Mehrere Modifier
laufen in Registrierungsreihenfolge, jeder sieht das Ergebnis des vorigen.

Das Regelwerk in `DisclosureRuleService` bleibt die eine Stelle, an der die
Begründung entsteht — ein Modifier verfeinert sie, er ersetzt sie nicht.

## Events

| Event | Wann | Zweck |
|---|---|---|
| `AfterLabelDecisionEvent` | nach jeder Entscheidung | Beobachten (zählen, protokollieren). Zum Ändern den Modifier nehmen. |
| `AfterStatusChangedEvent` | bei jeder Statusänderung | Reagieren: Dienst benachrichtigen, Cache verwerfen, Dashboard füttern |

`AfterStatusChangedEvent` wird aus dem Protokoll heraus ausgelöst und deckt
damit **alle** Wege ab: Formular, Massenbearbeitung, automatische Erkennung,
CLI und Meldungen anderer Extensions. Was im Protokoll steht, hat das Event
ausgelöst.

`AfterLabelDecisionEvent` feuert einmal je aufgelöster Datei — auf einer Seite
mit vielen Bildern entsprechend oft. Listener schlank halten.

## Icon in die Bilddatei einbrennen

`IconCompositorInterface` ist registriert und reicht standardmäßig unverändert
durch (`NullIconCompositor`). Ein zweites Paket überschreibt den Service-Alias
und übernimmt.

## Content Credentials anders auslesen

`C2paInspectorInterface` ist die Naht vor dem Auslesen der C2PA-Signatur. Das
Kernpaket belegt sie mit `C2paService`, der das lokale `c2patool` aufruft.

Der Grund für diese Naht ist handfest: Das Werkzeug ist gegen glibc gebunden
und braucht den dynamischen Loader unter `/lib64`. Auf gemanagten Hostings
fehlt er häufig, und keine Einstellung ändert daran etwas — die Signatur
bliebe dort schlicht ungelesen. Ein zweites Paket kann den Alias deshalb
dekorieren und die Prüfung anderswo erledigen:

```yaml
MeinPaket\Service\EigenerPruefer:
    decorates: NetThinks\NtAimark\Service\C2paInspectorInterface
    arguments:
        $local: '@.inner'
```

Dekoration statt Ersetzung ist der empfohlene Weg: So bleibt die lokale
Prüfung erreichbar, und die eigene Implementierung tritt nur an, wo sie
gebraucht wird.

Zwei Pflichten gehören dazu:

- **Keine Ausnahme nach außen.** Alles, was schiefgehen kann — fehlendes
  Werkzeug, Zeitüberschreitung, unerreichbarer Dienst, unsinnige Antwort —
  endet in `C2paState::NotVerifiable`. Ein Upload darf nicht daran scheitern,
  dass eine Signatur nicht geprüft werden konnte.
- **Nie mehr behaupten als geprüft wurde.** Ein Ergebnis darf einen Status
  *vorschlagen*; ob daraus eine Aussage über den Inhalt wird, bleibt eine
  menschliche Entscheidung. Bei gebrochener Signatur wird kein Status
  vorgeschlagen — was das Manifest über den Inhalt sagt, ist nicht mehr
  belastbar, sobald die Datei nicht mehr dazu passt.

`isAvailable()` wird bei jedem Aufruf des Backend-Moduls für den Systemstatus
gelesen. Es sollte ohne Nebenwirkungen und schnell antworten; wer einen
entfernten Dienst anspricht, schickt dafür keine Datei los.

## Fertiges HTML nachbearbeiten

Für Altprojekte mit gewachsenen Templates, in denen die ViewHelper nicht
gesetzt werden können. Zwei Bausteine stehen bereit:

- **Ein benannter Platz in der Middleware-Kette**:
  `netthinks/nt-aimark/label-injection`. Das Kernpaket reicht dort unverändert
  durch; wer `LabelInjectorInterface` als Service-Alias überschreibt,
  übernimmt — ohne eigene Middleware zu registrieren.
- **`ProcessedFileDeclarationResolver`**: findet zu einem Bildpfad aus dem
  fertigen HTML die zugehörige `AiDeclaration`, auch für skalierte Varianten
  über `sys_file_processedfile`.

Die Methode heißt bewusst `apply()` und nicht `inject()`: Symfony behandelt
Methoden, deren Name mit „inject" beginnt, als Setter-Injection, und der
Container verweigert dann den Dienst.

## Protokoll beschreiben

`AuditService` ist `public: true`, ein zweites Paket kann also eigene Einträge
schreiben. Die Auswertungsansicht (Audit-Log, Export) ist bewusst nicht Teil
des Kernpakets — geschrieben wird hier aber vollständig, damit die Daten
lückenlos vorliegen.

## Konfigurationsschlüssel

Schlüssel eines Zusatzpakets liegen im eigenen Namensraum (`aimarkPro.*`) und
tauchen im Kernpaket nicht auf.

## Warum es keine Lizenzprüfung gibt

TYPO3-Extensions, die Core-API nutzen, sind abgeleitete Werke und damit
GPL-2.0-or-later. Verkaufen ist erlaubt, die Weitergabe zu untersagen nicht.
Eine Laufzeitsperre wäre rechtlich angreifbar — und für ein Produkt, das mit
Rechtskonformität wirbt, die falsche Grundlage. Ein Test in diesem Repository
prüft, dass keinerlei Lizenz- oder Aktivierungslogik im Code auftaucht.
