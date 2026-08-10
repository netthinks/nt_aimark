<?php

declare(strict_types=1);

/**
 * Measures what survives TYPO3's image processing.
 *
 * TYPO3 strips profiles from processed images by default
 * (GFX/processor_stripColorProfileParameters, "+profile '*'"). If that removes
 * XMP and C2PA, then the machine-readable marking does not survive the first
 * f:image call — and the extension has to say so rather than promise otherwise.
 *
 * This script runs the same conversions TYPO3 would, across both processors
 * and with the strip parameter on and off, and reports what is left.
 *
 * Usage:
 *   php Build/experiments/metadata-survival.php [--input=/path/to/image.jpg]
 *
 * Without --input a JPEG carrying an XMP DigitalSourceType is generated. Pass a
 * C2PA-signed file to measure that leg as well — signing needs a certificate
 * and is out of scope here.
 */

const WIDTH = 200;

function jpegWithXmp(string $path): void
{
    $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<Iptc4xmpExt:DigitalSourceType>http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia</Iptc4xmpExt:DigitalSourceType>'
        . '<xmp:CreatorTool>nt_aimark experiment</xmp:CreatorTool>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';

    $payload = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
    $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

    $image = imagecreatetruecolor(400, 300);
    imagefill($image, 0, 0, (int)imagecolorallocate($image, 90, 140, 200));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string)ob_get_clean();
    imagedestroy($image);

    file_put_contents($path, "\xFF\xD8" . $app1 . substr($jpeg, 2));
}

function run(string $command): array
{
    exec($command . ' 2>&1', $output, $exitCode);

    return ['exit' => $exitCode, 'output' => implode("\n", $output)];
}

function hasXmp(string $path): bool
{
    $contents = (string)file_get_contents($path);

    return str_contains($contents, '<x:xmpmeta') || str_contains($contents, 'DigitalSourceType');
}

function c2paState(string $path): string
{
    $result = run(sprintf('c2patool %s', escapeshellarg($path)));

    if ($result['exit'] !== 0) {
        return str_contains($result['output'], 'No claim found') ? 'keine' : 'nicht prüfbar';
    }

    $report = json_decode($result['output'], true);

    return is_array($report) && isset($report['validation_state'])
        ? (string)$report['validation_state']
        : 'nicht lesbar';
}

function available(string $binary): bool
{
    return run(sprintf('command -v %s', escapeshellarg($binary)))['exit'] === 0;
}

// --------------------------------------------------------------------------

$options = getopt('', ['input::']);
$workDir = sys_get_temp_dir() . '/nt-aimark-metadata-' . getmypid() . '/';
mkdir($workDir, 0o777, true);

$source = $options['input'] ?? null;

if (!is_string($source) || !is_file($source)) {
    $source = $workDir . 'source.jpg';
    jpegWithXmp($source);
    echo "Eingabe: erzeugtes JPEG mit XMP DigitalSourceType\n";
} else {
    echo "Eingabe: {$source}\n";
}

$c2paAvailable = available('c2patool');

echo str_repeat('=', 78) . "\n";
printf("Ausgangsdatei      XMP: %-5s   C2PA: %s\n", hasXmp($source) ? 'ja' : 'nein', $c2paAvailable ? c2paState($source) : 'c2patool fehlt');
echo str_repeat('=', 78) . "\n\n";

// The strip parameter TYPO3 prepends by default.
$strip = "+profile '*'";

$cases = [];

if (available('convert')) {
    $cases['ImageMagick, mit Strip (Standard)'] = fn(string $in, string $out): string
        => sprintf('convert %s -quality 85 %s -geometry %dx -strip-placeholder %s', escapeshellarg($in), $strip, WIDTH, escapeshellarg($out));
    $cases['ImageMagick, ohne Strip'] = fn(string $in, string $out): string
        => sprintf('convert %s -quality 85 -geometry %dx %s', escapeshellarg($in), WIDTH, escapeshellarg($out));
}

if (available('gm')) {
    $cases['GraphicsMagick, mit Strip (Standard)'] = fn(string $in, string $out): string
        => sprintf('gm convert %s -quality 85 %s -geometry %dx %s', escapeshellarg($in), $strip, WIDTH, escapeshellarg($out));
    $cases['GraphicsMagick, ohne Strip'] = fn(string $in, string $out): string
        => sprintf('gm convert %s -quality 85 -geometry %dx %s', escapeshellarg($in), WIDTH, escapeshellarg($out));
}

$index = 0;

foreach ($cases as $label => $build) {
    $target = $workDir . 'out-' . ++$index . '.jpg';
    // The placeholder above keeps the ImageMagick argument order readable;
    // remove it before running.
    $command = str_replace(' -strip-placeholder', '', $build($source, $target));

    $result = run($command);

    if ($result['exit'] !== 0 || !is_file($target)) {
        printf("%-38s FEHLER: %s\n", $label, substr($result['output'], 0, 60));
        continue;
    }

    printf(
        "%-38s XMP: %-5s   C2PA: %s\n",
        $label,
        hasXmp($target) ? 'ja' : 'nein',
        $c2paAvailable ? c2paState($target) : '—',
    );
}

// Can the XMP packet be put back after processing? If so, the strip setting
// can stay as it is and only the fields that matter are restored.
echo "\n" . str_repeat('-', 78) . "\n";
echo "Rückschreiben des XMP-Pakets in die fertig verarbeitete Datei\n";
echo str_repeat('-', 78) . "\n";

$packet = extractApp1Xmp($source);

if ($packet === null) {
    echo "Kein XMP-APP1-Segment in der Ausgangsdatei — übersprungen.\n";
} else {
    $stripped = $workDir . 'out-1.jpg';

    if (!is_file($stripped)) {
        echo "Keine verarbeitete Datei vorhanden — übersprungen.\n";
    } else {
        $restored = $workDir . 'restored.jpg';
        copy($stripped, $restored);
        $written = injectApp1(($restored), $packet);

        printf(
            "Nach Rückschreiben                     XMP: %-5s   C2PA: %s   (%s)\n",
            hasXmp($restored) ? 'ja' : 'nein',
            $c2paAvailable ? c2paState($restored) : '—',
            $written ? 'geschrieben' : 'fehlgeschlagen',
        );
        printf(
            "Bild weiterhin lesbar: %s\n",
            @getimagesize($restored) === false ? 'NEIN' : 'ja',
        );
    }
}

echo "\nArbeitsverzeichnis: {$workDir}\n";

/**
 * Pulls the APP1 segment that carries the XMP packet out of a JPEG.
 */
function extractApp1Xmp(string $path): ?string
{
    $contents = (string)file_get_contents($path);
    $offset = 2;
    $length = strlen($contents);

    while ($offset + 4 <= $length && $contents[$offset] === "\xFF") {
        $marker = ord($contents[$offset + 1]);

        // Start of scan — no more metadata segments beyond this point.
        if ($marker === 0xDA) {
            break;
        }

        $size = unpack('n', substr($contents, $offset + 2, 2))[1] ?? 0;

        if ($size < 2) {
            break;
        }

        $segment = substr($contents, $offset, $size + 2);

        if ($marker === 0xE1 && str_contains($segment, 'http://ns.adobe.com/xap/1.0/')) {
            return $segment;
        }

        $offset += $size + 2;
    }

    return null;
}

/**
 * Puts a segment back directly after the SOI marker.
 */
function injectApp1(string $path, string $segment): bool
{
    $contents = (string)file_get_contents($path);

    if (!str_starts_with($contents, "\xFF\xD8")) {
        return false;
    }

    return file_put_contents($path, "\xFF\xD8" . $segment . substr($contents, 2)) !== false;
}
