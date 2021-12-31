<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidatePhpExtensions
{
    /**
     * @param $extensions
     *   The required module name.
     */
    public function __construct(
        public array $extensions,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('validate-php-extension', $args['extensions'] ?? $args[0]);
    }
}
