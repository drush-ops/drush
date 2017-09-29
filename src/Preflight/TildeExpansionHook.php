<?php

namespace Drush\Preflight;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\ValidatorInterface;

/**
 * The TildeExpansionHook is installed as a preValidate hook that runs before
 * all commands. Argument or option values containing a leading tilde will be expanded
 * to an absolute path.
 */
class TildeExpansionHook implements ValidatorInterface
{
    public function validate(CommandData $commandData) {
        $input = $commandData->input();
        $args = $input->getArguments();
        $options = $input->getOptions();
        $match = '#^~/#';
        $replacement = drush_server_home() . '/';
        foreach ($options as $name =>$value) {
            if (is_string($value) && preg_match($match, $value)) {
                $input->setOption($name, preg_replace($match, $replacement, $value));
            }
        }
        foreach ($args as $name =>$value) {
            if (is_string($value) && preg_match($match, $value)) {
                $input->setArgument($name, preg_replace($match, $replacement, $value));
            }
        }
    }
}
