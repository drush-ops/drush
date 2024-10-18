<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Consolidation\OutputFormatters\Options\FormatterOptions;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DefaultTableFields
{
    const KEY = FormatterOptions::DEFAULT_TABLE_FIELDS;

    /**
     * @param $fields
     *   An array of field names to show by default when using table formatter.
     */
    public function __construct(public array $fields)
    {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('default-table-fields', $args['fields']);
    }
}
