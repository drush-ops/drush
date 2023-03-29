<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateModulesEnabled
{
    /**
     * @param $modules
     *   The required module names.
     */
    public function __construct(
        public array $modules,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('validate-module-enabled', $args['modules'] ?? $args[0]);
    }
}
