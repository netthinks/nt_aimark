<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\DigitalSourceType;
use NetThinks\NtAimark\Domain\Model\ProvenanceResult;

/**
 * Reads the XMP packet embedded in a file and looks for the IPTC statement
 * about how the media was produced.
 *
 * PHP has no XMP reader, and pulling in a library for one field is not worth
 * it: the packet is plain XML delimited by a well-known marker, so it is
 * extracted directly.
 */
final readonly class XmpReaderService
{
    /** No point scanning a whole video for a header packet. */
    private const READ_LIMIT = 2_097_152;

    public function read(string $absolutePath): ProvenanceResult
    {
        $packet = $this->extractPacket($absolutePath);

        if ($packet === null) {
            return ProvenanceResult::nothing();
        }

        $xml = $this->parse($packet);

        if ($xml === null) {
            return ProvenanceResult::nothing();
        }

        $sourceType = $this->firstValue($xml, 'DigitalSourceType');

        if ($sourceType === '') {
            return ProvenanceResult::nothing();
        }

        return new ProvenanceResult(
            suggestedStatus: DigitalSourceType::toStatus($sourceType),
            system: $this->firstValue($xml, 'CreatorTool'),
            sourceType: $sourceType,
            createdAt: $this->createdAt($xml),
            detectedBy: 'xmp',
        );
    }

    private function extractPacket(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return null;
        }

        $head = (string) fread($handle, self::READ_LIMIT);
        fclose($handle);

        $start = strpos($head, '<x:xmpmeta');
        $end = strpos($head, '</x:xmpmeta>');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return substr($head, $start, $end - $start + strlen('</x:xmpmeta>'));
    }

    /**
     * Parsed with entity loading and network access off: the packet is
     * attacker-supplied data that arrives with an uploaded file.
     */
    private function parse(string $packet): ?\SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($packet, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (\Throwable) {
            $xml = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $xml === false ? null : $xml;
    }

    /**
     * XMP producers disagree on whether a field is an attribute or an element,
     * so both are searched — namespace-agnostically, because the prefix
     * (Iptc4xmpExt, xmp, …) is not fixed either.
     */
    private function firstValue(\SimpleXMLElement $xml, string $localName): string
    {
        $namespaces = $xml->getDocNamespaces(true);
        $namespaces = $namespaces === false ? [] : $namespaces;

        foreach ($namespaces as $namespace) {
            foreach ($xml->xpath('//*') ?: [] as $node) {
                foreach ($node->attributes($namespace) ?? [] as $name => $value) {
                    if ($name === $localName && (string) $value !== '') {
                        return trim((string) $value);
                    }
                }
            }
        }

        $matches = $xml->xpath(sprintf('//*[local-name()="%s"]', $localName)) ?: [];

        foreach ($matches as $match) {
            $value = trim((string) $match);

            if ($value !== '') {
                return $value;
            }

            // rdf:Alt / rdf:Seq wrappers put the value one level down.
            foreach ($match->xpath('.//*[local-name()="li"]') ?: [] as $item) {
                $itemValue = trim((string) $item);

                if ($itemValue !== '') {
                    return $itemValue;
                }
            }
        }

        return '';
    }

    private function createdAt(\SimpleXMLElement $xml): int
    {
        foreach (['CreateDate', 'DateCreated'] as $field) {
            $value = $this->firstValue($xml, $field);

            if ($value === '') {
                continue;
            }

            try {
                return (new \DateTimeImmutable($value))->getTimestamp();
            } catch (\Exception) {
                continue;
            }
        }

        return 0;
    }
}
