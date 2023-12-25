<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Drush;
use Drush\Utils\StringUtils;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateFileExists extends ValidatorBase
{
    /**
     * @param $argName
     *   The argument name containing the path to check.
     */
    public function __construct(
        public string $argName,
    ) {
    }

    public static function validate(CommandData $commandData, $argName)
    {
        $missing = [];
        if ($commandData->input()->hasArgument($argName)) {
            $path = $commandData->input()->getArgument($argName);
        } elseif ($commandData->input()->hasOption($argName)) {
            $path = $commandData->input()->getOption($argName);
        }
        if (!empty($path) && !file_exists($path)) {
            $missing[] = $path;
        }

        if ($missing) {
            $msg = dt('File(s) not found: !paths', ['!paths' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
