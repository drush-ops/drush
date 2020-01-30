<?php

namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Consolidation\SiteProcess\Util\Tty;
use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Utils\TerminalUtils;

/**
 * The RedispatchHook is installed as an init hook that runs before
 * all commands. If the commandline contains an alias or a site specification
 * that points at a remote machine, then we will stop execution of the
 * current command and instead run the command remotely.
 */
class RedispatchHook implements InitializeHookInterface, ConfigAwareInterface, SiteAliasManagerAwareInterface
{
    use ConfigAwareTrait;
    use SiteAliasManagerAwareTrait;

    /** @var ProcessManager */
    protected $processManager;

    public function __construct(ProcessManager $processManager)
    {
        $this->processManager = $processManager;
    }

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
        $aliasRecord = $this->siteAliasManager()->getSelf();
        // Determine if this is a remote command.
        if ($this->processManager->hasTransport($aliasRecord)) {
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
        // Get the command arguments, and shift off the Drush command.
        $redispatchArgs = $this->getConfig()->get('runtime.argv');
        $drush_path = array_shift($redispatchArgs);
        $command_name = $this->getConfig()->get('runtime.command');

        Drush::logger()->debug('Redispatch hook {command}', ['command' => $command_name]);

        // Remove argument patterns that should not be propagated
        $redispatchArgs = $this->alterArgsForRedispatch($redispatchArgs);

        // The options the user provided on the commandline will be included
        // in $redispatchArgs.
        $redispatchOptions = [];

        $aliasRecord = $this->siteAliasManager()->getSelf();
        $process = $this->processManager->drushSiteProcess($aliasRecord, $redispatchArgs, $redispatchOptions);
        if (!Tty::isTtySupported()) {
            $process->setInput(STDIN);
        } else {
            $process->setTty($this->getConfig()->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());

        return $this->exitEarly($process->getExitCode());
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
     * @param int $exit_code.
     */
    protected function exitEarly($exit_code)
    {
        Drush::logger()->debug('Redispatch hook exit early');

        // Note that RemoteCommandProxy::execute() is expecting that
        // the redispatch() method will not return, so that will need
        // to be altered if this behavior is changed.
        Runtime::setCompleted();
        exit($exit_code);
    }
}
