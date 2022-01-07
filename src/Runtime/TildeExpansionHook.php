<?php

namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\ValidatorInterface;
use Drush\Utils\StringUtils;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;

/**
 * The TildeExpansionHook is installed as a preValidate hook that runs before
 * all commands. Argument or option values containing a leading tilde will be expanded
 * to an absolute path.
 *
 * This is a pre-validate hook because we do not want to do tilde expansion
 * for commands that are redispatched to a remote site. That happens in the
 * RedispatchHook, which happens in hook init.
 */
class TildeExpansionHook implements ValidatorInterface, ConfigAwareInterface
{
    use ConfigAwareTrait;

    public function validate(CommandData $commandData): void
    {
        $input = $commandData->input();
        $args = $input->getArguments();
        $options = $input->getOptions();

        foreach ($options as $name => $value) {
            if (is_string($value)) {
                $replaced = StringUtils::replaceTilde($value, $this->getConfig()->home());
                if ($value !== $replaced) {
                    $input->setOption($name, $replaced);
                }
            }
        }
        foreach ($args as $name => $value) {
            if (is_string($value)) {
                $replaced = StringUtils::replaceTilde($value, $this->getConfig()->home());
                if ($value !== $replaced) {
                    $input->setArgument($name, $replaced);
                }
            }
        }
    }
}
