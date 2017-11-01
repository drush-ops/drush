<?php

namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\ValidatorInterface;
use Drush\Drush;
use Drush\Utils\StringUtils;

/**
 * The TildeExpansionHook is installed as a preValidate hook that runs before
 * all commands. Argument or option values containing a leading tilde will be expanded
 * to an absolute path.
 *
 * This is a pre-validate hook because we do not want to do tilde expansion
 * for commands that are redispatched to a remote site. That happens in the
 * RedispatchHook, which happens in hook init.
 */
class TildeExpansionHook implements ValidatorInterface
{
    public function validate(CommandData $commandData)
    {
        $input = $commandData->input();
        $args = $input->getArguments();
        $options = $input->getOptions();

        foreach ($options as $name => $value) {
            if (is_string($value)) {
                $input->setOption($name, StringUtils::replaceTilde($value));
            }
        }
        foreach ($args as $name => $value) {
            if (is_string($value)) {
                $input->setArgument($name, StringUtils::replaceTilde($value));
            }
        }
    }
}
