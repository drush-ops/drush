<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows one log record by id.
 *
 * @internal
 *
 * @deprecated in drush:14.0.0 and is removed from drush:15.0.0. Use
 *   `watchdog:show --id=ID` instead.
 */
#[AsCommand(
    name: self::NAME,
    description: 'Show one log record by ID. Deprecated: use `watchdog:show --id=ID`.',
    aliases: ['wd-one', 'watchdog-show-one'],
    usages: ['watchdog:show-one 42'],
)]
final class WatchdogShowOneCommand
{
    use AutowireTrait;
    use WatchdogTrait;

    public const string NAME = 'watchdog:show-one';

    public function __construct(
        protected Connection $connection,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('Watchdog Id')]
        int $id,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $this->requireDblog();

        $notice = (string) $this->t('watchdog:show-one is deprecated and will be removed in Drush 15. Use watchdog:show --id=@id instead.', ['@id' => $id]);
        @trigger_error($notice, E_USER_DEPRECATED);
        (new SymfonyStyle($input, $output))->getErrorStyle()->warning($notice);

        $output->writeln($this->renderRecord($this->loadRecord($id), $format), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
