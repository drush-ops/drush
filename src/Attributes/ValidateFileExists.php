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
class ValidateFileExists
{
    /**
     * @param $argName
     *   The argument name containing the path to check.
     */
    public function __construct(
        public string $argName,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        // @todo Maybe the caller should pass $hookManager into handle() method?
        /** @var HookManager $hookManager */
        $hookManager = Drush::getContainer()->get('hookManager');
        $hookManager->add(
            // Use a Closure to acquire $commandData and $args.
            fn(CommandData $commandData) => self::validate($commandData, $attribute->getArguments()),
            $hookManager::ARGUMENT_VALIDATOR,
            // @todo not currently a public property, and getName() gives an infinite loop because parsing still in progress.
            $commandInfo->name
        );
    }

    public static function validate(CommandData $commandData, array $args)
    {
        $missing = [];
        $arg_names =  StringUtils::csvToArray($args);
        foreach ($arg_names as $arg_name) {
            if ($commandData->input()->hasArgument($arg_name)) {
                $path = $commandData->input()->getArgument($arg_name);
            } elseif ($commandData->input()->hasOption($arg_name)) {
                $path = $commandData->input()->getOption($arg_name);
            }
            if (!empty($path) && !file_exists($path)) {
                $missing[] = $path;
            }
            unset($path);
        }

        if ($missing) {
            $msg = dt('File(s) not found: !paths', ['!paths' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
