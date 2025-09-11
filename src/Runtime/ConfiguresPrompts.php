<?php

namespace Drush\Runtime;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Laravel configures prompts in a trait, so we do too to keep code aligned.
 * This class exists to use the trait and define its dependent class properties.
 */
class ConfiguresPrompts
{
    use ConfiguresPromptsTrait;

    public function __construct(
        protected InputInterface $input,
        protected OutputInterface $output,
    ) {
    }
}
