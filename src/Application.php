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
        //   --remote-host
        //   --remote-user
        //   --simulate
        //
        // Functionality provided / subsumed by Symfony:
        //
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
        //   --interactive       If command isn't -n, then it is interactive
        //   --command-specific  Now handled by consolidation/config component
        //
        // Not handled yet (to be implemented):
        //
        //   --debug / -d
        //   --uri / -l
        //   --yes / -y
        //   --pipe
        //   --php
        //   --php-options
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
        //   --remote-os
        //   --site-list
        //   --reserve-margin
        //   --drush-coverage
        //
        //   --site-aliases
        //   --shell-aliases
        //   --path-aliases
        //   --ssh-options

        $this->getDefinition()
            ->addOption(
                new InputOption('--remote-host', null, InputOption::VALUE_REQUIRED, 'Run on a remote server.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--remote-user', null, InputOption::VALUE_REQUIRED, 'The user to use in remote execution.')
            );

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
