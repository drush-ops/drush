<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class InteractConfigName
{
    /**
     * @param string $argumentName
     *   The name of the argument which specifies the config ID.
     */
    public function __construct(
        public string $argumentName = 'config_name'
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addAnnotation('interact-config-name', $attribute->newInstance()->argumentName);
    }
}
