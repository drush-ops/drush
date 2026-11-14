<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

/**
 * Which tables a dump skips, dumps structure-only, or dumps at all.
 *
 * Built from the comma-separated --skip-tables-list, --structure-tables-list
 * and --tables-list options, whose entries may contain `*` wildcards, and
 * the list of tables that exist. Only existing tables are kept. When
 * --tables-list is given it wins over the other two, as in Drush.
 */
final readonly class TableSelection
{
    /**
     * @param string[] $skip
     * @param string[] $structure
     * @param string[] $tables
     */
    public function __construct(
        public array $skip = [],
        public array $structure = [],
        public array $tables = [],
    ) {
    }

    /**
     * @param string[] $existing
     *   The tables in the database.
     */
    public static function fromOptions(?string $skipList, ?string $structureList, ?string $tablesList, array $existing): self
    {
        return new self(
            self::expand($skipList, $existing),
            self::expand($structureList, $existing),
            self::expand($tablesList, $existing),
        );
    }

    /**
     * Expands wildcards against the existing tables and drops names that
     * match nothing.
     *
     * @param string[] $existing
     *
     * @return string[]
     */
    public static function expand(?string $list, array $existing): array
    {
        $patterns = array_filter(array_map(trim(...), explode(',', (string) $list)));
        $matched = [];
        foreach ($patterns as $pattern) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
            $matched = array_merge($matched, preg_grep($regex, $existing) ?: []);
        }
        $matched = array_values(array_unique($matched));
        sort($matched);
        return $matched;
    }
}
