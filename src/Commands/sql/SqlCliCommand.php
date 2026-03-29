<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\SiteProcess\Util\Tty;
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
        if (Tty::isTtySupported()) {
            $process->setTty((bool) $this->drushConfig->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());
        return Command::SUCCESS;
    }

    /**
     * Test if there is input waiting on STDIN
     */
    protected function hasPipedInput(): bool
    {
        $streams = [STDIN]; // note STDIN here is not a string
        $write_array = [];
        $except_array = [];
        $seconds = 0; // zero seconds on timeout since this is just for testing stream change
        $streamCount = @stream_select($streams, $write_array, $except_array, $seconds);

        return (bool) $streamCount;
    }
}
