<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Boot\Kernels;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Format
{
    /**
     * @param ?string $listDelimiter
     *    The delimiter between fields
     * @param ?string $tableStyle
     *    The table style.
     */
    public function __construct(
        public ?string $listDelimiter,
        # Sadly, \Symfony\Component\Console\Helper\Table::initStyles is private.
        #[ExpectedValues(['box', 'box-double', 'borderless', 'compact', 'consolidation'])] public ?string $tableStyle
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $instance = $attribute->newInstance();
        $commandInfo->addAnnotation(FormatterOptions::LIST_DELIMITER, $instance->listDelimiter);
        $commandInfo->addAnnotation(FormatterOptions::TABLE_STYLE, $instance->tableStyle);
    }
}
