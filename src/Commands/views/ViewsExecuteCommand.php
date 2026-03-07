<?php

declare(strict_types=1);

namespace Drush\Commands\views;

use Drupal\Core\Render\RendererInterface;
use Drupal\views\Views;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'views:execute',
    description: 'Execute a view and show a count of the results, or the rendered HTML.',
    aliases: ['vex', 'views-execute']
)]
#[CLI\ValidateEntityLoad(entityType: 'view', argumentName: 'view_name')]
#[CLI\ValidateModulesEnabled(modules: ['views'])]
final class ViewsExecuteCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'views:execute';

    public function __construct(
        protected RendererInterface $renderer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('view_name', InputArgument::REQUIRED, 'The name of the view to execute.')
            ->addArgument('display', InputArgument::OPTIONAL, 'The display ID to execute. If none specified, the default display will be used.')
            ->addArgument('view_args', InputArgument::OPTIONAL, 'A comma delimited list of values, corresponding to contextual filters.')
            ->addOption('count', null, InputOption::VALUE_NONE, 'Display a count of the results instead of each row.')
            ->addOption('show-admin-links', null, InputOption::VALUE_NONE, 'Show contextual admin links in the rendered markup.')
            ->addUsage('views:execute my_view')
            ->addUsage('views:execute my_view page_1 3 --count')
            ->addUsage('views:execute my_view page_1 3,foo');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $viewName = $input->getArgument('view_name');
        $display = $input->getArgument('display');
        $viewArgs = $input->getArgument('view_args');
        $showCount = $input->getOption('count');
        $showAdminLinks = $input->getOption('show-admin-links');

        $view = Views::getView($viewName);

        // Set the display and execute the view.
        $view->setDisplay($display);
        $view->preExecute(StringUtils::csvToArray($viewArgs));
        $view->execute();

        if (empty($view->result)) {
            $io->success('No results returned for this View.');
            return self::SUCCESS;
        } elseif ($showCount) {
            $io->writeln(count($view->result));
            return self::SUCCESS;
        } else {
            // Don't show admin links in markup by default.
            $view->hide_admin_links = !$showAdminLinks;
            $build = $view->preview();
            $rendered = (string) $this->renderer->renderInIsolation($build);
            $output->write($rendered);
            return self::SUCCESS;
        }
    }
}
