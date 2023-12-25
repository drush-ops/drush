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

    public function validate(CommandData $commandData)
    {
        $missing = array_filter($this->extensions, fn($extension) => !extension_loaded($extension));
        if ($missing) {
            $msg = dt('The following PHP extensions are required: !extensions', ['!extensions' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
