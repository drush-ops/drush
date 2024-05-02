<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\Util\Tty;
use Drush\Attributes\HandleRemoteCommands;
use Drush\Config\ConfigAwareTrait;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Console\Input\InputInterface;

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

    public function __construct(protected ProcessManager $processManager)
    {
    }

    /**
     * Check to see if it is necessary to redispatch to a remote site.
     *
     * We do not redispatch to local sites here; usually, local sites may
     * simply be selected and require no redispatch. When a local redispatch
     * is needed, it happens in the RedispatchToSiteLocal class.
     */
    public function initialize(InputInterface $input, AnnotationData $annotationData)
    {
        // See drush_preflight_command_dispatch; also needed are:
        //   - redispatch to a different site-local Drush on same system
        //   - site-list handling (REMOVED)
        // These redispatches need to be done regardless of the presence
        // of a HandleRemoteCommands Attribute.

        // If the command has the HandleRemoteCommands Attribute, then
        // short-circuit redispatches to remote hosts.
        if ($annotationData->has(HandleRemoteCommands::NAME)) {
            return;
        }
        return $this->redispatchIfRemote($input);
    }

    /**
     * Check to see if the target of the command is remote. Call redispatch
     * if it is.
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
     */
    public function redispatch(InputInterface $input): never
    {
        // Get the command arguments, and shift off the Drush command.
        $redispatchArgs = $this->getConfig()->get('runtime.argv');
        array_shift($redispatchArgs);
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
            // Command line options are always strings so cast - https://github.com/drush-ops/drush/issues/5798.
            $process->setTty((bool) $this->getConfig()->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());

        $this->exitEarly($process->getExitCode());
    }

    /**
     * Remove anything that is not necessary for the remote side.
     * At the moment this is limited to configuration options
     * provided via -D.
     */
    protected function alterArgsForRedispatch(array $redispatchArgs): array
    {
        return array_filter($redispatchArgs, function ($item) {
            return !str_starts_with($item, '-D');
        });
    }

    /**
     * Abort the current execution without causing distress to our
     * shutdown handler.
     *
     * @param int $exit_code.
     */
    protected function exitEarly(int $exit_code): never
    {
        Drush::logger()->debug('Redispatch hook exit early');

        // Note that RemoteCommandProxy::execute() is expecting that
        // the redispatch() method will not return, so that will need
        // to be altered if this behavior is changed.
        Runtime::setCompleted();
        exit($exit_code);
    }
}
