<?php

namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Drush\Drush;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Log\LogLevel;
use Robo\Common\ConfigAwareTrait;

/**
 * The RedispatchHook is installed as an init hook that runs before
 * all commands. If the commandline contains an alias or a site specification
 * that points at a remote machine, then we will stop execution of the
 * current command and instead run the command remotely.
 */
class RedispatchHook implements InitializeHookInterface, ConfigAwareInterface
{
    use ConfigAwareTrait;

    /**
     * Check to see if it is necessary to redispatch to a remote site.
     * We do not redispatch to local sites here; usually, local sites may
     * simply be selected and require no redispatch. When a local redispatch
     * is needed, it happens in the RedispatchToSiteLocal class.
     *
     * @param InputInterface $input
     * @param AnnotationData $annotationData
     */
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
        return $this->redispatchIfRemote($input);
    }

    /**
     * Check to see if the target of the command is remote. Call redispatch
     * if it is.
     *
     * @param InputInterface $input
     */
    public function redispatchIfRemote(InputInterface $input)
    {
        // Determine if this is a remote command.
        // n.b. 'hasOption' only means that the option definition exists, so don't use that here.
        $root = $input->getOption('remote-host');
        if (!empty($root)) {
            return $this->redispatch($input);
        }
    }

    /**
     * Called from RemoteCommandProxy::execute() to run remote commands.
     *
     * @param InputInterface $input
     */
    public function redispatch(InputInterface $input)
    {
        $remote_host = $input->getOption('remote-host');
        $remote_user = $input->getOption('remote-user');

        // Get the command arguments, and shift off the Drush command.
        $redispatchArgs = Drush::config()->get('runtime.argv');
        $drush_path = array_shift($redispatchArgs);
        $command_name = array_shift($redispatchArgs);

        Drush::logger()->debug('Redispatch hook {command}', ['command' => $command_name]);

        // Remove argument patterns that should not be propagated
        $redispatchArgs = $this->alterArgsForRedispatch($redispatchArgs);

        // The options the user provided on the commandline will be included
        // in $redispatchArgs.
        $redispatchOptions = [];

        // n.b. Defining the 'backend' flag here causes failed execution in the
        // non-interactive case, even if 'backend' is set to 'false'.
        $backend_options = [
            'drush-script' => $this->getConfig()->get('paths.drush-script', null),
            'remote-host' => $remote_host,
            'remote-user' => $remote_user,
            'additional-global-options' => [],
            'interactive' => true,
        ];
        $backend_options['#tty'] = $this->getConfig()->get('ssh.tty', $input->isInteractive());
        if ($input->isInteractive()) {
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

    /**
     * Remove anything that is not necessary for the remote side.
     * At the moment this is limited to configuration options
     * provided via -D.
     *
     * @param array $redispatchArgs
     */
    protected function alterArgsForRedispatch($redispatchArgs)
    {
        return array_filter($redispatchArgs, function ($item) {
            return strpos($item, '-D') !== 0;
        });
    }

    /**
     * Abort the current execution without causing distress to our
     * shutdown handler.
     *
     * @param array $values The results from backend invoke.
     */
    protected function exitEarly($values)
    {
        Drush::logger()->log(LogLevel::DEBUG, 'Redispatch hook exit early');

        // TODO: This is how Drush exits from redispatch commands today;
        // perhaps this could be somewhat improved, though.
        // Note that RemoteCommandProxy::execute() is expecting that
        // the redispatch() method will not return, so that will need
        // to be altered if this behavior is changed.
        drush_set_context('DRUSH_EXECUTION_COMPLETED', true);
        exit($values['error_status']);
    }
}
