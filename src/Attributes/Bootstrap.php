<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Boot\DrupalBoot;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Bootstrap implements AttributeInterface
{
    public const LEVELS = ['none', 'max', 'root', 'site', 'configuration', 'database', 'full'];

    /**
     * @param $level
     *   The level name to bootstrap to.
     */
    public function __construct(
        #[ExpectedValues(self::LEVELS)] public string $level,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('bootstrap', $args['level']);
    }
}
