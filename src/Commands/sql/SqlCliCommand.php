<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Sql\SqlBase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Open a SQL command-line interface using Drupal\'s credentials.',
    aliases: ['sqlc', 'sql-cli'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
#[CLI\HelpLinks(links: [HelpLinks::Policy])]
final class SqlCliCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    public const string NAME = 'sql:cli';

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly ProcessManager $processManager,
        protected readonly DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'extra', mode: InputOption::VALUE_REQUIRED, description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')
            ->addUsage('sql:cli --extra=-A')
            ->addUsage('$(drush sql:connect) < example.sql')
            ->addUsage('eval (drush sql:connect) < example.sql')
            ->setHelp('To import an SQL dump, it is more efficient to use sql:connect than sql:cli. See the Examples below.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->hasPipedInput()) {
            throw new RuntimeException('Instead of piping SQL to sql:cli, it is faster to use sql:connect. See the Examples at https://www.drush.org/latest/commands/sql_connect/#examples');
        }

        $sql = SqlBase::create($input->getOptions());
        $program = $sql->command();
        if (!self::programExists($program)) {
            $msg = dt('The shell command \'!command\' is required but cannot be found. Please install it and retry.', ['!command' => $program]);
            throw new RuntimeException($msg);
        }

        $process = $this->processManager->shell($sql->connect(), null, $sql->getEnv());
        // Enable TTY for interactive use if supported.
        if (\Symfony\Component\Process\Process::isTtySupported()) {
            $process->setTty(true);
        }
        $process->mustRun($process->showRealtime());
        return Command::SUCCESS;
    }

    /**
     * Test if there is input waiting on STDIN.
     */
    protected function hasPipedInput(): bool
    {
        // If STDIN is connected to a TTY, there's definitely no piped input.
        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            return false;
        }

        // In containerized environments (Docker, ddev, etc.), stream_select() returns
        // false positives because stdin is a pipe/socket rather than a TTY, even when
        // no data is being piped. Since we cannot reliably distinguish between:
        // 1. Interactive use in a container (no piped data, but stdin is not a TTY)
        // 2. Actual piped input (data is being sent via stdin)
        // We disable the piped input check when not on a TTY. Users who pipe input
        // will simply use a slightly less efficient path, which is acceptable.
        return false;
    }
}
