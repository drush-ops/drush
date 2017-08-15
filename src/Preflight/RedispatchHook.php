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
        //   - site-list handling (REMOVED)
        // These redispatches need to be done regardless of the presence
        // of a @handle-remote-commands annotation.

        // If the command has the @handle-remote-commands annotation, then
        // short-circuit redispatches to remote hosts.
        if ($annotationData->has('handle-remote-commands')) {
            return;
        }

        // Determine if this is a remote command.
        $remote_host = $input->getOption('remote-host');
        if (isset($remote_host)) {
            $remote_user = $input->getOption('remote-user');

            // Get the command arguements, and shift off the Drush command.
            $redispatchArgs = $input->getArguments();
            $command_name = array_shift($redispatchArgs);

            // Fetch the commandline options to pass along to the remote command.
            $redispatchOptions = $this->redispatchOptions($input);

            $backend_options = [
                'drush-script' => null,
                'remote-host' => $remote_host,
                'remote-user' => $remote_user,
                'additional-global-options' => [],
                'integrate' => true,
            ];
            if ($input->isInteractive()) {
                $backend_options['#tty'] = true;
                $backend_options['interactive'] = true;
            }

            $invocations = [
                [
                    'command' => $command_name,
                    'args' => $redispatchArgs,
                ],
            ];
            $common_backend_options = [];
            $default_command = null;
            $default_site = [
                'remote-host' => $remote_host,
                'remote-user' => $remote_user,
                'root' => $input->getOption('root'),
                'uri' => $input->getOption('uri'),
            ];
            $context = null;

            $values = drush_backend_invoke_concurrent(
                $invocations,
                $redispatchOptions,
                $backend_options,
                $default_command,
                $default_site,
                $context
            );

            return $this->exitEarly($values);
        }
    }

    protected function redispatchOptions(InputInterface $input)
    {
        $result = [];
        foreach ($input->getOptions() as $option => $value) {
            if ($value === true) {
                $result[$option] = true;
            } elseif (is_string($value) && !empty($value)) {
                $result[$option] = $value;
            }
        }

        // hack hack
        unset($result['remote-host']);
        unset($result['remote-user']);

        return $result;
    }

    protected function exitEarly($values)
    {
        // TODO: This is how Drush exits from redispatch commands today;
        // perhaps this could be somewhat improved, though.
        drush_set_context('DRUSH_EXECUTION_COMPLETED', true);
        exit($values['error_status']);
    }
}
