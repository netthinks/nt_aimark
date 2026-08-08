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

## Testabdeckung

Die Reihenfolge ist durch eine ausgeschriebene Matrix über alle Kombinationen
aus KI-Anteil × Kennzeichnungsmodus × Stichtag abgedeckt
(`Tests/Unit/Service/DisclosureRuleServiceTest.php`). Die Erwartungen sind
notiert, nicht berechnet — eine geänderte Regelreihenfolge fällt damit als
fehlschlagende Zeile auf, statt stillschweigend mitzuwandern.
