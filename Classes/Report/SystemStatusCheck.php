<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Report;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Service\C2paInspectorDescriptionInterface;
use NetThinks\NtAimark\Service\C2paInspectorInterface;
use NetThinks\NtAimark\Service\C2paInspectorProbeInterface;
use NetThinks\NtAimark\Service\ExtensionSettings;
use NetThinks\NtAimark\Service\IconResolverService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Tells the operator what is missing before anything silently degrades.
 *
 * Every optional dependency this extension has degrades quietly by design — a
 * missing icon becomes a text label, a missing binary becomes "not verifiable".
 * That is the right behaviour at runtime and the wrong one to leave
 * undocumented, so it is collected here.
 *
 * Pure computation, no rendering: the backend module and a future status
 * report use the same findings.
 */
final readonly class SystemStatusCheck
{
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_NOTICE = 'notice';
    public const SEVERITY_WARNING = 'warning';

    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_mod.xlf:';

    /**
     * Wie lange eine Erreichbarkeitsprobe gilt.
     *
     * Fünf Minuten sind der Ausgleich zwischen zwei Fehlern: Eine Probe je
     * Modulaufruf hinge das Backend an einen fremden Server — bei fünf
     * Redakteuren fünffach. Eine Probe je Tag wäre so alt, dass niemand ihr
     * glaubt. So kostet es höchstens zwölf Anfragen in der Stunde, ganz gleich
     * wie viele Leute hinsehen.
     */
    private const PROBE_LIFETIME = 300;

    public function __construct(
        private IconResolverService $iconResolver,
        private C2paInspectorInterface $c2paService,
        private ExtensionSettings $settings,
        private ?FrontendInterface $cache = null,
    ) {}

    /**
     * Verwirft die gespeicherte Probe, damit die nächste Abfrage wirklich
     * nachsieht. Für die Schaltfläche „Jetzt prüfen".
     */
    public function forgetProbe(): void
    {
        $this->cache?->remove('c2pa-probe');
    }

    /**
     * @return list<array{severity: string, titleKey: string, detailKey: string, detail: string, hintKey?: string, hintUrl?: string, probeDone?: bool, probeOk?: bool, probeAgeKey?: string, probeAgeValue?: string}>
     */
    public function findings(): array
    {
        return array_values(array_filter([
            $this->euIcons(),
            $this->c2patool(),
            $this->imageProcessing(),
            $this->exifExtension(),
        ]));
    }

    public function hasWarnings(): bool
    {
        foreach ($this->findings() as $finding) {
            if ($finding['severity'] === self::SEVERITY_WARNING) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function euIcons(): array
    {
        $missing = $this->iconResolver->missingVariants();

        if ($missing === []) {
            return $this->ok('status.icons');
        }

        return [
            'severity' => self::SEVERITY_WARNING,
            'titleKey' => self::LL . 'status.icons',
            'detailKey' => self::LL . 'status.icons.detail',
            'detail' => implode(', ', array_map(
                static fn(IconVariant $variant): string => $variant->value,
                $missing,
            )),
        ];
    }

    /**
     * The most common finding on shared hosting, and the one an operator can
     * do least about.
     *
     * c2patool is a static Rust binary that still needs a glibc loader under
     * /lib64. Plenty of managed hosts do not have one, and no setting fixes
     * that — so the notice carries the way out with it instead of leaving the
     * reader with "not available". The link is a plain hint; the extension
     * works on without it, and an empty addOnInfoUrl removes it.
     *
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string, hintKey?: string, hintUrl?: string, probeDone?: bool, probeOk?: bool, probeAgeKey?: string, probeAgeValue?: string}
     */
    private function c2patool(): array
    {
        if ($this->c2paService->isAvailable()) {
            $befund = $this->ok('status.c2patool');

            // "In Ordnung" allein deckt zwei Faelle ab, die ein Betreiber
            // auseinanderhalten koennen muss: Die Datei wird auf dem eigenen
            // Server gelesen — oder sie wird dafuer woanders hingeschickt. Bei
            // einer Extension, deren Gegenstand Transparenz ist, waere das
            // Verschweigen die falsche Art von Stille.
            if ($this->c2paService instanceof C2paInspectorDescriptionInterface) {
                $befund['detailKey'] = self::LL . 'status.c2patool.where';
                $befund['detail'] = $this->c2paService->describeInspection();
            }

            $probe = $this->probe();

            if ($probe !== null) {
                // Eigenes Merkmal statt einer Prüfung auf das Alter: Eine
                // frische Probe ist null Sekunden alt, und Fluid hält 0 für
                // falsch — die Anzeige wäre ausgerechnet direkt nach dem
                // Prüfen verschwunden.
                $befund['probeDone'] = true;
                $befund['probeOk'] = $probe['ok'];

                [$schluessel, $wert] = $this->alterInWorten(max(0, time() - $probe['time']));
                $befund['probeAgeKey'] = self::LL . $schluessel;
                $befund['probeAgeValue'] = $wert;

                // Eine fehlgeschlagene Probe wiegt schwerer als die
                // Konfiguration: Eingerichtet zu sein und zu antworten sind
                // zwei verschiedene Dinge, und nur das zweite hilft.
                if (!$probe['ok']) {
                    $befund['severity'] = self::SEVERITY_WARNING;
                }
            }

            return $befund;
        }

        $finding = [
            'severity' => self::SEVERITY_NOTICE,
            'titleKey' => self::LL . 'status.c2patool',
            'detailKey' => self::LL . 'status.c2patool.detail',
            'detail' => $this->settings->c2patoolPath(),
        ];

        $url = $this->settings->addOnInfoUrl();

        if ($url !== '') {
            $finding['hintKey'] = self::LL . 'status.c2patool.hint';
            $finding['hintUrl'] = $url;
        }

        return $finding;
    }

    /**
     * Sekunden in etwas, das ein Mensch lesen mag.
     *
     * „vor 13746 s" ist keine Angabe, sondern eine Zumutung — niemand rechnet
     * im Kopf in Stunden um. Die Einzahl bekommt einen eigenen Schlüssel, weil
     * „vor 1 Minuten" falsch ist und nicht mit einem Platzhalter zu retten.
     *
     * Stunden sind nach der Reparatur der Verfallszeit eigentlich nicht mehr
     * erreichbar — sie bleiben als Sicherung, falls jemand PROBE_LIFETIME
     * heraufsetzt.
     *
     * @return array{0: string, 1: string}
     */
    private function alterInWorten(int $sekunden): array
    {
        if ($sekunden < 60) {
            return ['status.probe.age.now', ''];
        }

        $minuten = intdiv($sekunden, 60);

        if ($minuten < 60) {
            return $minuten === 1
                ? ['status.probe.age.minute', '1']
                : ['status.probe.age.minutes', (string) $minuten];
        }

        $stunden = intdiv($minuten, 60);

        return $stunden === 1
            ? ['status.probe.age.hour', '1']
            : ['status.probe.age.hours', (string) $stunden];
    }

    /**
     * Die zwischengespeicherte Erreichbarkeitsprobe.
     *
     * Ohne Cache wird nicht geprüft: Eine ungepufferte Netzanfrage bei jedem
     * Modulaufruf ist genau das, was hier vermieden werden soll.
     *
     * @return array{ok: bool, time: int}|null
     */
    private function probe(): ?array
    {
        if (!$this->c2paService instanceof C2paInspectorProbeInterface || $this->cache === null) {
            return null;
        }

        $gespeichert = $this->cache->get('c2pa-probe');

        // Das Alter wird hier geprüft und nicht dem Cache überlassen: Die
        // Voreinstellung nutzt SimpleFileBackend, und das kennt keine
        // Verfallszeit. Die beim Schreiben angegebene Lebensdauer wurde
        // stillschweigend ignoriert, die Probe blieb stundenlang stehen. So
        // hängt die Auffrischung nicht daran, welches Backend jemand
        // konfiguriert hat.
        if (is_array($gespeichert) && isset($gespeichert['ok'], $gespeichert['time'])) {
            $alter = time() - (int) $gespeichert['time'];

            if ($alter >= 0 && $alter < self::PROBE_LIFETIME) {
                return ['ok' => (bool) $gespeichert['ok'], 'time' => (int) $gespeichert['time']];
            }
        }

        $ergebnis = ['ok' => $this->c2paService->probeReachable(), 'time' => time()];
        $this->cache->set('c2pa-probe', $ergebnis, [], self::PROBE_LIFETIME);

        return $ergebnis;
    }

    /**
     * TYPO3 strips profiles from processed images by default, which takes the
     * XMP packet with it. Either the restore is on, or the operator should
     * know the marking does not survive scaling.
     *
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function imageProcessing(): array
    {
        $gfx = $GLOBALS['TYPO3_CONF_VARS']['GFX'] ?? [];
        $stripsProfiles = (bool) ($gfx['processor_stripColorProfileByDefault'] ?? true);

        if (!$stripsProfiles || $this->settings->preserveMetadata()) {
            return $this->ok('status.metadata');
        }

        return [
            'severity' => self::SEVERITY_WARNING,
            'titleKey' => self::LL . 'status.metadata',
            'detailKey' => self::LL . 'status.metadata.detail',
            'detail' => 'processor_stripColorProfileByDefault',
        ];
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function exifExtension(): array
    {
        return function_exists('exif_read_data')
            ? $this->ok('status.exif')
            : [
                'severity' => self::SEVERITY_NOTICE,
                'titleKey' => self::LL . 'status.exif',
                'detailKey' => self::LL . 'status.exif.detail',
                'detail' => 'ext-exif',
            ];
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function ok(string $titleKey): array
    {
        return [
            'severity' => self::SEVERITY_OK,
            'titleKey' => self::LL . $titleKey,
            'detailKey' => '',
            'detail' => '',
        ];
    }
}
