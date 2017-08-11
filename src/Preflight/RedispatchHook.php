<?php

namespace Drush\Preflight;

use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Symfony\Component\Console\Input\InputInterface;
use Consolidation\AnnotatedCommand\AnnotationData;

/**
 * The RedispatchHook is installed as an init hook that runs before
 * all commands. If the commandline contains an alias or a site specification
 * that points at a remote machine, then we will stop execution of the
 * current command and instead run the command remotely.
 */
class RedispatchHook implements InitializeHookInterface
{
    public function __construct()
    {
    }

    public function initialize(InputInterface $input, AnnotationData $annotationData)
    {
        // See drush_preflight_command_dispatch; also needed are:
        //   - redispatch to a different site-local Drush on same system
        //   - site-list handling
        // These redispatches need to be done regardless of the presence
        // of a @handle-remote-commands annotation.

        // If the command has the @handle-remote-commands annotation, then
        // short-circuit redispatches to remote hosts.
        if ($annotationData->has('handle-remote-commands')) {
            return;
        }

        // Determine if this is a remote command.
        if ($input->hasOption('remote-host')) {
            $remote_host = $input->getOption('remote-host');
            $remote_user = $input->getOption('remote-user');

            // TODO: All commandline options to pass along to the remote command.
            $args = [];

            $command_name = $input->getFirstArgument();
            $user_interactive = $input->isInteractive();

            $values = drush_do_command_redispatch($command_name, $args, $remote_host, $remote_user, $user_interactive);
            return $this->exitEarly($values);
        }
    }

    protected function exitEarly($values)
    {
        // TODO: This is how Drush exits from redispatch commands today;
        // perhaps this could be somewhat improved, though.
        drush_set_context('DRUSH_EXECUTION_COMPLETED', true);
        exit($values['error_status']);
    }
}
