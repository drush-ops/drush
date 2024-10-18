<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Consolidation\OutputFormatters\Options\FormatterOptions;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FieldLabels
{
    const KEY = FormatterOptions::FIELD_LABELS;

    /**
     * @param $labels
     *   An associative array of field names and labels for display.
     */
    public function __construct(
        public array $labels
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('field-labels', $args['labels']);
    }
}
