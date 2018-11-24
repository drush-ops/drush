<?php

namespace Drush\Symfony;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * This is an InputDefinition that allows any option to be considered valid.
 * Used when passing a command through to another dispatcher that will do
 * the option validation.
 *
 * We use this instead of a LessStrictArgvInput in cases where we do not
 * know in advance whether the input should be handled indiscriminately.
 * In other words, an IndiscriminateInputDefinition is attached to individual
 * Commands that should accept any option, whereas a LessStrictArgvInput
 * should be used to make all command skip option validation.
 */
class IndiscriminateInputDefinition extends InputDefinition
{
    /**
     * @inheritdoc
     */
    public function hasShortcut($name)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasOption($name)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getOption($name)
    {
        if (parent::hasOption($name)) {
            return parent::getOption($name);
        }
        return new InputOption($name, null, InputOption::VALUE_OPTIONAL, '', []);
    }
}
