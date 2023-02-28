<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class HookSelector
{
    /**
     * @param $name
     *  The hook target which is being selected. If a hook has a target that begins with @, the remainder of the target is the name of the hook selector that must be present for that hook to run.
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
