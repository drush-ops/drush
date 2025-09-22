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
use Symfony\Component\Console\Input\StreamableInputInterface;
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

    public const NAME = 'sql:cli';

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
        $sql = SqlBase::create($input->getOptions());
        $program = $sql->command();
        if (!self::programExists($program)) {
            $msg = dt('The shell command \'!command\' is required but cannot be found. Please install it and retry.', ['!command' => $program]);
            throw new RuntimeException($msg);
        }

        $process = $this->processManager->shell($sql->connect(), null, $sql->getEnv());
        if (!Tty::isTtySupported()) {
            $this->logger->warning('It is slow to pass large amounts of data via stdin to the sql:cli command. See the Examples at https://www.drush.org/latest/commands/sql_cli/ for an alternative using sql:connect.');
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // If testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : null;
            // If nothing from input stream use STDIN instead.
            $inputStream = $inputStream ?? STDIN;
            // $data = stream_get_contents($inputStream);
            $process->setInput($inputStream);
        } else {
            $process->setTty((bool) $this->drushConfig->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());
        return Command::SUCCESS;
    }
}
