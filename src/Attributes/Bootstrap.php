<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Boot\DrupalBootLevels;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Bootstrap
{
    /**
     * @param $level
     *   The level to bootstrap to.
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: DrupalBootLevels::class)] public string $level,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('bootstrap', $args['level']);
    }
}
