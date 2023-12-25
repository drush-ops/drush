<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateModulesEnabled extends ValidatorBase
{
    /**
     * @param $modules
     *   The required module names.
     */
    public function __construct(
        public array $modules,
    ) {
    }

    public static function validate(CommandData $commandData, \ReflectionAttribute $attribute)
    {
        $instance = $attribute->newInstance();
        $modules = $instance->modules;
        $missing = array_filter($modules, function ($module) {
            return !\Drupal::moduleHandler()->moduleExists($module);
        });
        if ($missing) {
            $msg = dt('The following modules are required: !modules', ['!modules' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
