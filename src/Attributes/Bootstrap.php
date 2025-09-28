<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Boot\DrupalBootLevels;
use JetBrains\PhpStorm\ExpectedValues;
use RuntimeException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Bootstrap
{
    /**
     * @param $level
     *   The level to bootstrap to.
     * @param int|null $max_level
     *   A maximum level when used with MAX.
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: DrupalBootLevels::class)] public int $level,
        public ?int $max_level = null,
    ) {
        if ($this->max_level && $this->level !== DrupalBootLevels::MAX) {
            throw new RuntimeException('The max_level argument can only be used with the MAX bootstrap level.');
        }
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $instance = $attribute->newInstance();
        $commandInfo->addAnnotation('bootstrap', $instance->level . ( isset($instance->max_level) ? " $instance->max_level" : ''));
    }
}
