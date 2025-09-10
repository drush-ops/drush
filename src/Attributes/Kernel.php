<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Boot\Kernels;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\ExpectedValues;

#[Deprecated('Replace with an initialize() method on a Console command. See \Drush\Commands\core\UpdateDbStatusCommand::initialize')]
#[Attribute(Attribute::TARGET_METHOD)]
class Kernel
{
    /**
     * @param $name
     *   The kernel name.
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: Kernels::class)] public string $name,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('kernel', $args['name']);
    }
}
