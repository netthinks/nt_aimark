# Regelwerk

Der `DisclosureRuleService` beantwortet eine einzige Frage: *Wird diese Datei
gekennzeichnet, und wie?* Die Reihenfolge der Prüfungen ist bindend — sie
trägt die inhaltlichen Zusagen der Extension.

## Prüfreihenfolge

Die erste zutreffende Regel entscheidet.

| # | Bedingung | Ergebnis | Begründungscode |
|---|---|---|---|
| 1 | Kennzeichnung = „Nicht kennzeichnen" | kein Label | `manual_exempt` |
| 2 | Erzeugt vor dem 02.08.2026 | kein Label | `pre_cutoff` |
| 3 | KI-Anteil = Ungeprüft **oder** Vorschlag | kein Label, gilt als offener Vorgang | `unreviewed` |
| 4 | KI-Anteil = Kein KI-Einsatz | kein Label | `no_ai` |
| 5 | Kennzeichnung = „Immer kennzeichnen" | Label | `manual_forced` |
| 6 | KI-Anteil = KI-generiert oder KI-bearbeitet | Label | `rule_default` |
| 7 | KI-Anteil = Herkunft unbekannt | je nach Einstellung, standardmäßig kein Label | `unknown_origin` |

Der Begründungscode wandert ins Protokoll. Damit ist rekonstruierbar, warum
ein Label erschienen ist — und warum eines ausgeblieben ist.

## Warum die Reihenfolge so ist

**Regel 3 vor allem, was ein Label erzeugt.** Ein Vorschlag der automatischen
Erkennung ist eine Vermutung. Würde er gerendert, gäbe die Website eine
Vermutung als Feststellung aus. Deshalb steht die Prüfung auf „noch nicht
bestätigt" vor jedem Zweig, der kennzeichnet.

**Regel 5 nach den Regeln 2 bis 4.** „Immer kennzeichnen" ist eine
redaktionelle Verstärkung, kein Generalschlüssel. Es kann weder den Stichtag
aushebeln noch eine als KI-frei eingestufte Datei kennzeichnen noch einen
ungeprüften Datensatz veröffentlichen.

**Regel 7 abschaltbar, standardmäßig aus.** „Herkunft unbekannt" ist eine
andere Aussage als „KI war beteiligt". Wer beides gleichsetzt, behauptet
etwas, das er nicht weiß. Wenn Sie das für Ihren Bestand dennoch wollen,
schalten Sie `ntAimark.labelUnknownOrigin` ein.

## Ein leeres Erzeugungsdatum ist keine Ausnahme

`0` bedeutet „nicht erfasst", nicht „vor dem Stichtag". Andernfalls würde
jede Datei, bei der niemand das Datum eingetragen hat, stillschweigend von
der Kennzeichnung befreit.

## Welches Symbol

1. Hat die Redaktion ein Symbol gewählt, gilt dieses — auch die Wahl „Kein
   Symbol" für eine reine Textkennzeichnung.
2. Sonst folgt es dem KI-Anteil: KI-generiert → `AI GENERATED`,
   KI-bearbeitet → `AI MODIFIED`, Herkunft unbekannt → `AI`.
3. Fehlt die Icon-Datei, wird statt des Symbols Text ausgegeben.

## Was in die Detailebene darf

Nur, was ein Besucher sehen soll: eingesetztes System mit Anbieter und das
Erzeugungsdatum. **Prompt und interne Notiz erreichen das Markup nie**; ein
Test sichert das ab.

---

# Regelwerk für Texte

Die Pflicht für Texte ist anders geschnitten als die für Medien: Sie greift
nur bei **Angelegenheiten von öffentlichem Interesse** und entfällt, wenn ein
Mensch den Text geprüft hat **und** jemand dafür benannt ist.

| # | Bedingung | Ergebnis | Begründungscode |
|---|---|---|---|
| 1 | KI-Anteil = Kein KI-Einsatz | kein Hinweis | `no_ai` |
| 2 | Keine Angelegenheit von öffentlichem Interesse | kein Hinweis | `not_public_interest` |
| 3 | Redaktionell geprüft **und** Person benannt | kein Hinweis | `editorial_control` |
| 4 | Redaktionell geprüft, aber niemand benannt | **Hinweis** | `editorial_control_incomplete` |
| 5 | Sonst | Hinweis | `rule_default` |

## Warum Regel 4 so ausfällt

Die Ausnahme lebt davon, dass jemand für die Prüfung einsteht. Ein Häkchen
ohne Namen dokumentiert nichts. Der Text wird deshalb weiter gekennzeichnet,
und der Fall ist als unvollständige Ausnahme erkennbar, damit die Lücke
geschlossen werden kann statt unbemerkt zu bleiben. Das TCA verlangt den Namen
folgerichtig, sobald das Häkchen gesetzt ist.

## Warum es für Texte keine Stichtagsregel gibt

Bei Medien entscheidet der Zeitpunkt der Erzeugung. Bei Texten zählt
**zusätzlich** der Zeitpunkt der Veröffentlichung — und ein Text, der auf einer
Website steht, wird laufend veröffentlicht. Daraus eine automatische Ausnahme
zu bauen wäre eine rechtliche Auslegung, und die trifft die Extension nicht.
Redaktionen haben die Felder, um ihre eigene Entscheidung festzuhalten.

## Was im Frontend erscheint

Ein Satz, kein Symbol: Bei einem Text ist eine Formulierung das, was die
Kennzeichnung beim Erstkontakt verständlich macht. Die verantwortliche Person
ist interne Nachweisführung und erscheint **nicht** im Frontend.

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}
<nt:textNotice record="{data}" table="tt_content" />
```

---

## Testabdeckung

Die Reihenfolge ist durch eine ausgeschriebene Matrix über alle Kombinationen
aus KI-Anteil × Kennzeichnungsmodus × Stichtag abgedeckt
(`Tests/Unit/Service/DisclosureRuleServiceTest.php`). Die Erwartungen sind
notiert, nicht berechnet — eine geänderte Regelreihenfolge fällt damit als
fehlschlagende Zeile auf, statt stillschweigend mitzuwandern.

Für Texte gilt dasselbe in `TextDisclosureRuleServiceTest.php`. Ein Test prüft
dort zusätzlich, dass wirklich jede Kombination der drei gespeicherten Felder
in der Matrix vorkommt — eine Zeilenzahl allein sagt darüber nichts aus.
