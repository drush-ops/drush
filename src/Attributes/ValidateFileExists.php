<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateFileExists
{
    /**
     * @param $argName
     *   The argument name containing the path to check.
     */
    public function __construct(
        public string $argName,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo): void
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('validate-file-exists', $args['argName']);
    }
}
