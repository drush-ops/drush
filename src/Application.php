<?php
namespace Drush;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class Application extends SymfonyApplication
{
    /**
     * @param string $name
     * @param string $version
     */
    public function __construct($name, $version)
    {
        parent::__construct($name, $version);

        // TODO: Add all of Drush's global options that are NOT handled
        // by PreflightArgs here.

        //
        // All legacy global options from drush_get_global_options() in drush.inc:
        //
        // Options handled by PreflightArgs:
        //
        //   --root / -r
        //   --include
        //   --config
        //   --alias-path
        //   --local
        //
        // Global options registerd with Symfony:
        //
        //   --simulate
        //
        // Functionality provided / subsumed by Symfony:
        //
        //   --debug / -d
        //   --verbose / -v
        //   --help
        //   --quiet
        //
        // No longer supported
        //
        //   --no / -n           Now, -n is --no-interaction
        //   --search-depth      We could just decide the level we will search for aliases
        //   --show-invoke
        //   --early             Completion handled by standard symfony extension
        //   --complete-debug
        //   --strict            Not supported by Symfony
        //
        // Not handled yet (to be implemented):
        //
        //   --uri / -l
        //   --yes / -y
        //   --pipe
        //   --php
        //   --php-options
        //   --interactive
        //   --tty
        //   --exclude
        //   --backend
        //   --choice
        //   --ignored-modules
        //   --no-label
        //   --label-separator
        //   --nocolor
        //   --cache-default-class
        //   --cache-class-<bin>
        //   --confirm-rollback
        //   --halt-on-error
        //   --deferred-sanitization
        //   --remote-host
        //   --remote-user
        //   --remote-os
        //   --site-list
        //   --reserve-margin
        //   --drush-coverage
        //
        //   --command-specific
        //   --site-aliases
        //   --shell-aliases
        //   --path-aliases
        //   --ssh-options

        $this->getDefinition()
            ->addOption(
                new InputOption('--simulate', null, InputOption::VALUE_NONE, 'Run in simulated mode (show what would have happened).')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--define', '-D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a configuration item value.', [])
            );
    }

    /**
     * @inheritdoc
     */
    public function find($name)
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException $e) {
            print "TOOD: bootstrap further.\n";
            // TODO: if the command was not found, and a bootstrap object
            // is available, then bootstrap some more and try to
            // find the requested command again. If things still do not
            // pan out, re-throw the CommandNotFoundException.
            throw $e;
        }
    }
}
