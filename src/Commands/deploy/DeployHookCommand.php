<?php

declare(strict_types=1);

namespace Drush\Commands\deploy;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Utility\Error;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Run pending deploy update hooks.',
)]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
#[CLI\Version(version: '10.3')]
final class DeployHookCommand extends Command
{
    use AutowireTrait;
    use DeployTrait;

    public const NAME = 'deploy:hook';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly LoggerInterface $logger,
        private readonly ProcessManager $processManager,
        private readonly DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $pending = $this->getRegistry()->getPendingUpdateFunctions();

        if (empty($pending)) {
            $this->logger->notice('No pending deploy hooks.');
            return Command::SUCCESS;
        }

        $process = $this->processManager->drush($this->siteAliasManager->getSelf(), DeployHookStatusCommand::NAME, [], Drush::redispatchOptions() + ['strict' => 0]);
        $process->mustRun();
        $output->writeln($process->getOutput());

        if (!$io->confirm('Do you wish to run the specified pending deploy hooks?')) {
            throw new \Exception('Deploy hooks cancelled by user.');
        }

        $success = true;
        if (!$this->drushConfig->simulate()) {
            $operations = [];
            foreach ($pending as $function) {
                // We need to pass a string callable to it can be serialized by DatabaseQueue
                $operations[] = [DeployHookCommand::class . '::updateDoOneDeployHook', [$function]];
            }

            $batch = [
                'operations' => $operations,
                'title' => 'Updating',
                'init_message' => 'Starting deploy hooks',
                'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
                // We need to pass a string callable to it can be serialized by DatabaseQueue
                'finished' => DeployHookCommand::class . '::updateFinished',
            ];
            batch_set($batch);
            $result = drush_backend_batch_process(DeployHookBatchProcessCommand::NAME);

            $success = false;
            if (!is_array($result)) {
                $this->logger->error(sprintf('Batch process did not return a result array. Returned: %s', gettype($result)));
            } elseif (!empty($result[0]['#abort'])) {
                // Whenever an error occurs the batch process does not continue, so
                // this array should only contain a single item, but we still output
                // all available data for completeness.
                $this->logger->error(sprintf('Update aborted by: %s', implode(', ', $result[0]['#abort'])));
            } else {
                $success = true;
            }
        }

        $message = 'Finished performing deploy hooks.';
        if ($success) {
            $io->success($message);
        } else {
            $this->logger->error($message);
        }
        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Batch command that executes a single deploy hook.
     */
    public static function updateDoOneDeployHook(string $function, array $context): void
    {
        $ret = [];

        // If this update was aborted in a previous step, or has a dependency that was
        // aborted in a previous step, go no further.
        if (!empty($context['results']['#abort'])) {
            return;
        }

        // Module names can include '_deploy', so deploy functions like
        // module_deploy_deploy_name() are ambiguous. Check every occurrence.
        $components = explode('_', $function);
        foreach (array_keys($components, 'deploy', true) as $position) {
            $module = implode('_', array_slice($components, 0, $position));
            $name = implode('_', array_slice($components, $position + 1));
            $filename = $module . '.deploy';
            \Drupal::moduleHandler()->loadInclude($module, 'php', $filename);
            if (function_exists($function)) {
                break;
            }
        }
        assert(isset($module) && isset($name) && isset($filename));

        if (function_exists($function)) {
            if (empty($context['results'][$module][$name]['type'])) {
                Drush::logger()->notice("Deploy hook started: $function");
            }
            try {
                $ret['results']['query'] = $function($context['sandbox']);
                $ret['results']['success'] = true;
                $ret['type'] = 'deploy';

                if (!isset($context['sandbox']['#finished']) || (isset($context['sandbox']['#finished']) && $context['sandbox']['#finished'] >= 1)) {
                    self::getRegistry()->registerInvokedUpdates([$function]);
                }
            } catch (\Exception $e) {
                // @TODO We may want to do different error handling for different exception
                // types, but for now we'll just log the exception and return the message
                // for printing.
                // @see https://www.drupal.org/node/2564311
                Drush::logger()->error($e->getMessage());

                $variables = Error::decodeException($e);
                $variables = array_filter($variables, function ($key) {
                    return $key[0] === '@' || $key[0] === '%';
                }, ARRAY_FILTER_USE_KEY);
                // On windows there is a problem with json encoding a string with backslashes.
                $variables['%file'] = strtr($variables['%file'], [DIRECTORY_SEPARATOR => '/']);
                $ret['#abort'] = [
                    'success' => false,
                    'query' => strip_tags((string) t('%type: @message in %function (line %line of %file).', $variables)),
                ];
            }
        } else {
            $ret['#abort'] = ['success' => false];
            Drush::logger()->warning(dt('Deploy hook function @function not found in file @filename', [
                '@function' => $function,
                '@filename' => "$filename.php",
            ]));
        }

        if (isset($context['sandbox']['#finished'])) {
            $context['finished'] = $context['sandbox']['#finished'];
            unset($context['sandbox']['#finished']);
        }
        if (!isset($context['results'][$module][$name])) {
            $context['results'][$module][$name] = [];
        }
        $context['results'][$module][$name] = array_merge($context['results'][$module][$name], $ret);

        // Log the message that was returned.
        if (!empty($ret['results']['query'])) {
            Drush::logger()->notice(strip_tags((string) $ret['results']['query']));
        }

        if (!empty($ret['#abort'])) {
            // Record this function in the list of updates that were aborted.
            $context['results']['#abort'][] = $function;
            Drush::logger()->error("Deploy hook failed: $function");
        } elseif ($context['finished'] == 1 && empty($ret['#abort'])) {
            $context['message'] = "Performed: $function";
        }
    }

    /**
     * Batch finished callback.
     *
     * @param boolean $success Whether the batch ended without a fatal error.
     */
    public static function updateFinished(bool $success, array $results, array $operations): void
    {
        // In theory there is nothing to do here.
    }
}
