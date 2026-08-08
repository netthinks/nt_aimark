<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Configuration;

/**
 * The tables that carry the text transparency fields.
 *
 * `pages` and `tt_content` always do. Anything else — news records, custom
 * models — is added through the extension configuration, so a project can
 * cover its own content types without a patch.
 */
final class TextTableRegistry
{
    /** @var list<string> */
    public const CORE_TABLES = ['pages', 'tt_content'];

    public const COLUMNS = [
        'tx_ntaimark_text_status',
        'tx_ntaimark_public_interest',
        'tx_ntaimark_editorial_control',
        'tx_ntaimark_responsible',
    ];

    /**
     * @return list<string>
     */
    public static function all(string $configured = ''): array
    {
        return array_values(array_unique([...self::CORE_TABLES, ...self::additional($configured)]));
    }

    /**
     * Table names end up in DDL and in TCA keys, so anything that is not a
     * plausible identifier is dropped rather than passed on.
     *
     * @return list<string>
     */
    public static function additional(string $configured): array
    {
        $tables = [];

        foreach (explode(',', $configured) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '' || in_array($candidate, self::CORE_TABLES, true)) {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $candidate) === 1) {
                $tables[] = $candidate;
            }
        }

        return array_values(array_unique($tables));
    }
}
