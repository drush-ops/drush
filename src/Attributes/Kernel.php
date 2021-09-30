<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Boot\DrupalBoot;
use Drush\Boot\DrupalBootLevels;
use Drush\Boot\Kernels;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Kernel
{
    /**
     * @param $kernel
     *   The kernel name.
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: Kernels::class)] public string $kernel,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('kernel', $args['kernel']);
    }
}
