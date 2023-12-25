<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidatePhpExtensions extends ValidatorBase implements ValidatorInterface
{
    /**
     * @param $extensions
     *   The required module name.
     */
    public function __construct(
        public array $extensions,
    ) {
    }

    public static function validate(CommandData $commandData, \ReflectionAttribute $attribute)
    {
        $instance = $attribute->newInstance();
        $extensions = $instance->extensions;
        $missing = array_filter($extensions, function ($extension) {
            return !extension_loaded($extension);
        });
        if ($missing) {
            $msg = dt('The following PHP extensions are required: !extensions', ['!extensions' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
