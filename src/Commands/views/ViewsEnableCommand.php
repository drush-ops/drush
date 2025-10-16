<?php

declare(strict_types=1);

namespace Drush\Commands\views;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Enable the specified views.',
    aliases: ['ven', 'views-enable']
)]
#[CLI\ValidateEntityLoad(entityType: 'view', argumentName: 'views')]
final class ViewsEnableCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'views:enable';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('views', InputArgument::REQUIRED, 'A comma delimited list of view names.')
            ->addUsage('ven frontpage,taxonomy_term');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $viewsArg = $input->getArgument('views');

        $view_names = StringUtils::csvToArray($viewsArg);
        if ($views = $this->entityTypeManager->getStorage('view')->loadMultiple($view_names)) {
            foreach ($views as $view) {
                $view->enable();
                $view->save();
            }
        }

        $io->success(sprintf('%s enabled.', implode(', ', $view_names)));

        return self::SUCCESS;
    }
}
