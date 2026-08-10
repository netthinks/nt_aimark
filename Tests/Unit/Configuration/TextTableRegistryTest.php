<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Configuration;

use NetThinks\NtAimark\Configuration\TextTableRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TextTableRegistryTest extends UnitTestCase
{
    #[Test]
    public function theCoreTablesAreAlwaysIncluded(): void
    {
        self::assertSame(['pages', 'tt_content'], TextTableRegistry::all(''));
    }

    #[Test]
    public function configuredTablesAreAppended(): void
    {
        self::assertSame(
            ['pages', 'tt_content', 'tx_news_domain_model_news'],
            TextTableRegistry::all('tx_news_domain_model_news'),
        );
    }

    #[Test]
    public function whitespaceAndEmptyEntriesAreIgnored(): void
    {
        self::assertSame(
            ['tx_news_domain_model_news', 'tx_blog_domain_model_post'],
            TextTableRegistry::additional('  tx_news_domain_model_news , , tx_blog_domain_model_post ,'),
        );
    }

    #[Test]
    public function theCoreTablesAreNotListedTwice(): void
    {
        self::assertSame([], TextTableRegistry::additional('pages, tt_content'));
        self::assertSame(['pages', 'tt_content'], TextTableRegistry::all('pages,tt_content'));
    }

    #[Test]
    public function duplicatesCollapse(): void
    {
        self::assertSame(['tx_a_table'], TextTableRegistry::additional('tx_a_table, tx_a_table'));
    }

    /**
     * The value reaches DDL and TCA keys, and a project's configuration is not
     * necessarily free of typos or worse.
     *
     * @return array<string, array{string}>
     */
    public static function implausibleNames(): array
    {
        return [
            'sql fragment' => ['tx_a; DROP TABLE pages'],
            'backtick' => ['`pages`'],
            'space inside' => ['tx a table'],
            'leading digit' => ['1table'],
            'uppercase' => ['TxNewsDomainModelNews'],
            'too short' => ['ab'],
            'hyphen' => ['tx-news'],
            'quote' => ["tx_news'"],
        ];
    }

    #[Test]
    #[DataProvider('implausibleNames')]
    public function anythingThatIsNotAPlausibleTableNameIsDropped(string $candidate): void
    {
        self::assertSame([], TextTableRegistry::additional($candidate));
    }

    /**
     * The registry and ext_tables.sql have to agree: a column declared in one
     * and missing from the other means a configured extra table gets different
     * fields than pages and tt_content.
     */
    #[Test]
    public function everyRegisteredColumnExistsInTheSchema(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/ext_tables.sql');
        $ttContent = substr($schema, (int) strpos($schema, 'CREATE TABLE tt_content'));
        $ttContent = substr($ttContent, 0, (int) strpos($ttContent, ');'));

        foreach (TextTableRegistry::COLUMNS as $column) {
            self::assertStringContainsString($column, $ttContent, $column . ' is missing from ext_tables.sql.');
        }

        // And nothing in the table is missing from the registry.
        preg_match_all('/(tx_ntaimark_\w+)/', $ttContent, $matches);

        foreach (array_unique($matches[1]) as $column) {
            self::assertContains($column, TextTableRegistry::COLUMNS, $column . ' is missing from the registry.');
        }
    }
}
