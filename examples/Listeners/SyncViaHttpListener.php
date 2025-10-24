<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Drush;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;

/**
 *  To temporarily use this Listener, use the --include option - e.g. `drush --include=/path/to/drush/examples sql:sync`
 */
#[AsEventListener(method: 'onDefinitions')]
#[AsEventListener(method: 'onConsoleCommand')]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class SyncViaHttpListener
{
    use \Drush\Commands\AutowireTrait;
    use ExecTrait;

    public function __construct(
        protected ProcessManager $processManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function onDefinitions(ConsoleDefinitionsEvent $event): void
    {

        $command = $event->getApplication()->get('sql:sync');
        $command->addOption(name: 'http-sync-url', mode: InputOption::VALUE_REQUIRED, description: 'Url that the existing database dump can be found at.');
        $command->addOption(name: 'http-sync-user', mode: InputOption::VALUE_REQUIRED, description: 'Username for the protected directory containing the sql dump.');
        $command->addOption(name: 'http-sync-password', mode: InputOption::VALUE_REQUIRED, description: 'Password for the same directory.');
    }

    /**
     * Determine if the http-sync-url option has been
     * specified.  If it has, disable the normal ssh + rsync
     * dump-and-transfer that sql:sync usually does, and transfer the
     * database dump via an http download.
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()->getName() !== 'sql:sync') {
            return;
        }

        $input = $event->getInput();
        $sql_dump_download_url = $input->getOption('http-sync-url');
        if ($sql_dump_download_url) {
            $this->logger->info(dt('Downloading sql dump from !url', ['!url' => $sql_dump_download_url]));
            $user = $input->getOption('http-sync-user');
            $password = $input->getOption('http-sync-password');
            $source_dump_file = $this->downloadFile($sql_dump_download_url, $user, $password);
            $input->setOption('target-dump', $source_dump_file);
            $input->setOption('no-dump', true);
            $input->setOption('no-sync', true);
        }
    }

    /**
     * Downloads a file.
     *
     * Optionally uses user authentication, using either wget or curl, as available.
     */
    protected function downloadFile($url, $user = false, $password = false, $destination = false, $overwrite = true)
    {
        static $use_wget;
        if ($use_wget === null) {
            $use_wget = self::programExists('wget');
        }

        $destination_tmp = drush_tempnam('download_file');
        if ($use_wget) {
            $args = ['wget', '-q', '--timeout=30'];
            if ($user && $password) {
                $args = array_merge($args, ["--user=$user", "--password=$password", '-O', $destination_tmp, $url]);
            } else {
                $args = array_merge($args, ['-O', $destination_tmp, $url]);
            }
        } else {
            $args = ['curl', '-s', '-L', '--connect-timeout 30'];
            if ($user && $password) {
                $args = array_merge($args, ['--user', "$user:$password", '-o', $destination_tmp, $url]);
            } else {
                $args = array_merge($args, ['-o', $destination_tmp, $url]);
            }
        }
        $process = $this->processManager->process($args);
        $process->mustRun();

        if (!Drush::simulate()) {
            if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
                @file_put_contents($destination_tmp, $file);
            }
            if (!drush_file_not_empty($destination_tmp)) {
                // Download failed.
                throw new \Exception(dt("The URL !url could not be downloaded.", ['!url' => $url]));
            }
        }
        if ($destination) {
            $fs = new Filesystem();
            $fs->rename($destination_tmp, $destination, $overwrite);
            return $destination;
        }
        return $destination_tmp;
    }
}
