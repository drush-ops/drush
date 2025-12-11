<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_CLASS)]
class TableFormat implements FormatterConfigurationItemProviderInterface
{
    const KEY = FormatterOptions::TABLE_STYLE;

    /**
     * @param ?string $listDelimiter
     *    The delimiter between fields
     * @param ?string $tableStyle
     *    The table style.
     */
    public function __construct(
        public ?string $listDelimiter,
        # Sadly, \Symfony\Component\Console\Helper\Table::initStyles is private.
        #[ExpectedValues(['box', 'box-double', 'borderless', 'compact', 'consolidation'])] public ?string $tableStyle,
        public bool $include_field_labels = true,
    ) {
    }

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        /** @var TableFormat $args */
        $args = $attribute->newInstance();
        return [
            FormatterOptions::TABLE_STYLE => $args->tableStyle,
            FormatterOptions::LIST_DELIMITER => $args->listDelimiter,
            FormatterOptions::INCLUDE_FIELD_LABELS => $args->include_field_labels,
        ];
    }
}
