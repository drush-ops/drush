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
     * @param $value
     *   A value which should can be used by the hook.
     */
    public function __construct(
        public string $name,
        public ?string $value,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $instance = $attribute->newInstance();
        $commandInfo->addAnnotation($instance->name, $instance->value);
    }
}
