# Metadaten und Bildverarbeitung — Messergebnis

Dies ist der Befund zu der Frage, die als größtes technisches Risiko des
Projekts galt: **Überlebt die maschinenlesbare Markierung die Bildverarbeitung
von TYPO3?**

Kurze Antwort: **XMP ja, wenn man etwas dafür tut. C2PA nein — und das ist
richtig so.**

---

## Ausgangslage

TYPO3 entfernt beim Skalieren standardmäßig alle Profile. Maßgeblich ist
`$GLOBALS['TYPO3_CONF_VARS']['GFX']`:

| Einstellung | Standard |
|---|---|
| `processor_stripColorProfileByDefault` | `true` |
| `processor_stripColorProfileParameters` | `['+profile', '*']` |

`+profile '*'` entfernt sämtliche eingebetteten Profile — Farbprofil, EXIF,
IPTC **und XMP**.

## Messung

Gemessen mit `Build/experiments/metadata-survival.php`. Das Skript schickt
eine Datei durch dieselben Konvertierungen, die TYPO3 aufruft, einmal mit und
einmal ohne den Strip-Parameter, für beide Prozessoren.

Umgebung: ImageMagick 7.1.1-43, GraphicsMagick 1.4 (snapshot 2025-03-31),
c2patool 0.27.7, Debian im DDEV-Webcontainer.

Eingabe: JPEG mit XMP `Iptc4xmpExt:DigitalSourceType` **und** gültigem,
signiertem C2PA-Manifest (`validation_state: Valid`).

| Verarbeitung | XMP | C2PA |
|---|---|---|
| Ausgangsdatei | vorhanden | **Valid** |
| ImageMagick, mit Strip (Standard) | **weg** | weg |
| ImageMagick, ohne Strip | vorhanden | weg |
| GraphicsMagick, mit Strip (Standard) | **weg** | weg |
| GraphicsMagick, ohne Strip | vorhanden | **Invalid** |
| ImageMagick mit Strip, danach XMP zurückgeschrieben | **vorhanden** | weg |

## Was daraus folgt

### XMP überlebt nicht, lässt sich aber zurückschreiben

Mit der Standardkonfiguration ist das XMP-Paket nach dem ersten `f:image`
verschwunden. Zwei Wege führen zurück:

1. **Strip global abschalten.** Wirkt, ist aber ein grober Eingriff: dann
   bleiben auch Farbprofile und EXIF in jeder abgeleiteten Datei, was die
   Dateien unnötig aufbläht. Die Einstellung existiert nicht ohne Grund.
2. **Paket gezielt zurückschreiben.** Die Extension geht diesen Weg. Das
   APP1-Segment wird nach der Verarbeitung wieder in die abgeleitete
   JPEG-Datei eingesetzt; die Strip-Konfiguration von TYPO3 bleibt
   unangetastet.

Der zweite Weg ist gemessen: XMP wieder vorhanden, Bild weiterhin lesbar.

### C2PA überlebt nicht — und darf es auch nicht

Das ist der wichtigere Teil des Befundes.

Eine C2PA-Signatur bestätigt kryptografisch, dass die Bilddaten unverändert
sind. Nach dem Skalieren stimmt sie zwangsläufig nicht mehr. Die Messung
zeigt das ungeschönt: **GraphicsMagick ohne Strip überträgt das Manifest
tatsächlich mit — und das Ergebnis validiert als `Invalid`.** Die abgeleitete
Datei behauptet dann von sich, manipuliert worden zu sein.

Ein Manifest unverändert mitzukopieren wäre also nicht nur nutzlos, sondern
eine falsche Aussage über die Datei. Die Extension überträgt C2PA deshalb
**nie**. Ein Test hält das fest.

Die saubere Alternative wäre, für die abgeleitete Datei ein **neues** Manifest
zu erzeugen, das die Skalierung als Bearbeitungsschritt ausweist. Das setzt
ein Signaturzertifikat voraus und ist in v1.0 nicht enthalten.

### Für die Produktbeschreibung

- Zulässig: „Die relevanten XMP-Angaben bleiben in skalierten Bildern
  erhalten."
- **Nicht zulässig**: „Content Credentials bleiben erhalten." Das tun sie
  nicht, und keine Einstellung ändert daran etwas.

Wichtig für die Einordnung: Die sichtbare Kennzeichnung nach Art. 50 Abs. 4
kommt **ohne Metadaten aus**. Sie entsteht aus den Angaben in der Datenbank,
nicht aus der Bilddatei. Der Metadaten-Erhalt ist eine Zugabe für die
maschinenlesbare Ebene, kein Fundament der Kennzeichnung.

## Grenzen der Umsetzung

- **Nur JPEG.** XMP in PNG oder WebP zurückzuschreiben heißt,
  Container-Chunks umzuschreiben — eine andere Aufgabe. Bei anderen Formaten
  lehnt der Dienst ab, statt die Datei zu beschädigen.
- **Abschaltbar** über die Extension-Einstellung *XMP in verarbeiteten
  Bildern wiederherstellen*.
- Geschrieben wird erst in eine temporäre Datei; erst wenn das Ergebnis noch
  als Bild lesbar ist, ersetzt es die Zieldatei. Ein nicht darstellbares
  Vorschaubild wäre deutlich schlimmer als ein fehlendes XMP-Paket.
- Trägt die verarbeitete Datei bereits ein Paket, wird kein zweites ergänzt.

## Messung wiederholen

```bash
php Build/experiments/metadata-survival.php
php Build/experiments/metadata-survival.php --input=/pfad/zu/signiertem-bild.jpg
```

Ohne `--input` erzeugt das Skript ein JPEG mit XMP. Für die C2PA-Spalte wird
eine signierte Datei benötigt; das Signieren selbst setzt ein Zertifikat
voraus und ist nicht Teil des Skripts.

---

## Nebenbefund zur Umgebung

Im Workspace `netthinks-14` steht `GFX/processor_path` auf `/usr/local/bin/`,
ImageMagick liegt dort aber nicht — die Binärdateien sind unter `/usr/bin/`.
Das betrifft nicht diese Extension, sollte aber geprüft werden.
