<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\DigitalSourceType;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Service\XmpReaderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class XmpReaderServiceTest extends UnitTestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/nt-aimark-xmp-' . uniqid('', true) . '/';
        mkdir($this->directory, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * Writes a JPEG carrying an XMP packet. The reader scans the leading bytes
     * for the packet marker, which is what real files look like.
     */
    private function fileWithPacket(string $packet): string
    {
        $path = $this->directory . 'image-' . uniqid('', true) . '.jpg';
        file_put_contents($path, "\xFF\xD8\xFF\xE0" . $packet . "\xFF\xD9");

        return $path;
    }

    private function packet(string $body): string
    {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . $body
            . '</rdf:RDF></x:xmpmeta>';
    }

    /**
     * @return array<string, array{string, ?AiStatus}>
     */
    public static function sourceTypes(): array
    {
        return [
            'generated' => [DigitalSourceType::TRAINED_ALGORITHMIC_MEDIA, AiStatus::Generated],
            'modified' => [DigitalSourceType::COMPOSITE_WITH_TRAINED_ALGORITHMIC_MEDIA, AiStatus::Modified],
            'algorithmic' => [DigitalSourceType::ALGORITHMIC_MEDIA, AiStatus::Generated],
        ];
    }

    #[Test]
    #[DataProvider('sourceTypes')]
    public function theSourceTypeIsReadFromAnElement(string $uri, ?AiStatus $expected): void
    {
        $path = $this->fileWithPacket($this->packet(
            '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                . '<Iptc4xmpExt:DigitalSourceType>' . $uri . '</Iptc4xmpExt:DigitalSourceType>'
                . '</rdf:Description>',
        ));

        $result = (new XmpReaderService())->read($path);

        self::assertSame($expected, $result->suggestedStatus);
        self::assertSame($uri, $result->sourceType);
        self::assertSame('xmp', $result->detectedBy);
    }

    /**
     * Producers disagree on whether the field is an element or an attribute.
     */
    #[Test]
    public function theSourceTypeIsAlsoReadFromAnAttribute(): void
    {
        $path = $this->fileWithPacket($this->packet(
            '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                . ' Iptc4xmpExt:DigitalSourceType="' . DigitalSourceType::TRAINED_ALGORITHMIC_MEDIA . '" />',
        ));

        self::assertSame(AiStatus::Generated, (new XmpReaderService())->read($path)->suggestedStatus);
    }

    #[Test]
    public function theCreationDateAndCreatorToolAreCarriedAlong(): void
    {
        $path = $this->fileWithPacket($this->packet(
            '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
                . '<Iptc4xmpExt:DigitalSourceType>' . DigitalSourceType::TRAINED_ALGORITHMIC_MEDIA . '</Iptc4xmpExt:DigitalSourceType>'
                . '<xmp:CreatorTool>Midjourney</xmp:CreatorTool>'
                . '<xmp:CreateDate>2026-09-14T10:11:12Z</xmp:CreateDate>'
                . '</rdf:Description>',
        ));

        $result = (new XmpReaderService())->read($path);

        self::assertSame('Midjourney', $result->system);
        self::assertSame(
            (new \DateTimeImmutable('2026-09-14T10:11:12Z'))->getTimestamp(),
            $result->createdAt,
        );
    }

    /**
     * A source type that says nothing about AI must not produce a suggestion.
     */
    #[Test]
    public function aPlainPhotographProducesNoSuggestion(): void
    {
        $path = $this->fileWithPacket($this->packet(
            '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                . '<Iptc4xmpExt:DigitalSourceType>http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture</Iptc4xmpExt:DigitalSourceType>'
                . '</rdf:Description>',
        ));

        $result = (new XmpReaderService())->read($path);

        self::assertNull($result->suggestedStatus);
    }

    #[Test]
    public function aFileWithoutAPacketYieldsNothing(): void
    {
        $path = $this->directory . 'plain.jpg';
        file_put_contents($path, "\xFF\xD8\xFF\xE0 just pixels \xFF\xD9");

        self::assertFalse((new XmpReaderService())->read($path)->hasFinding());
    }

    #[Test]
    public function aMissingFileYieldsNothing(): void
    {
        self::assertFalse((new XmpReaderService())->read($this->directory . 'nope.jpg')->hasFinding());
    }

    #[Test]
    public function brokenXmlYieldsNothingRatherThanAnError(): void
    {
        $path = $this->fileWithPacket('<x:xmpmeta xmlns:x="adobe:ns:meta/"><unclosed></x:xmpmeta>');

        self::assertFalse((new XmpReaderService())->read($path)->hasFinding());
    }

    /**
     * The packet arrives with an uploaded file, so it is attacker-controlled
     * input. External entities must not be resolved.
     */
    #[Test]
    public function externalEntitiesAreNotResolved(): void
    {
        $secret = $this->directory . 'secret.txt';
        file_put_contents($secret, 'TOP-SECRET-CONTENT');

        $path = $this->fileWithPacket(
            '<?xml version="1.0"?>'
                . '<!DOCTYPE x:xmpmeta [<!ENTITY xxe SYSTEM "file://' . $secret . '">]>'
                . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
                . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                . '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                . '<Iptc4xmpExt:DigitalSourceType>&xxe;</Iptc4xmpExt:DigitalSourceType>'
                . '</rdf:Description></rdf:RDF></x:xmpmeta>',
        );

        $result = (new XmpReaderService())->read($path);

        self::assertStringNotContainsString('TOP-SECRET-CONTENT', $result->sourceType);
        self::assertStringNotContainsString('TOP-SECRET-CONTENT', $result->system);
    }
}
