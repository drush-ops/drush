<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class HookCustom
{
    /**
     * @param $name
     *  The hook name which is being implemented.
     * @param $arguments
     *   Arguments which should be passed along to the hook implementation.
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $instance = $attribute->newInstance();
        $commandInfo->addAnnotation($instance->name, implode(' ', $instance->arguments));
    }
}
