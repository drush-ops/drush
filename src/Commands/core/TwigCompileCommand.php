<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(
    name: self::NAME,
    description: 'Compile Twig templates.',
    aliases: ['twigc', 'twig-compile'],
)]
class TwigCompileCommand extends Command
{
    use AutowireTrait;

    const NAME = 'twig:compile';

    protected function __construct(
        private readonly LoggerInterface $logger,
        protected TwigEnvironment $twig,
        protected ModuleHandlerInterface $moduleHandler,
        private readonly ModuleExtensionList $extensionList,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $searchpaths = [];
        require_once DRUPAL_ROOT . "/core/themes/engines/twig/twig.engine";
        // Scan all enabled modules and themes.
        $modules = array_keys($this->moduleHandler->getModuleList());
        foreach ($modules as $module) {
            $searchpaths[] = $this->extensionList->getPath($module);
        }

        $themes = \Drupal::service('theme_handler')->listInfo();
        foreach ($themes as $name => $theme) {
            $searchpaths[] = $theme->getPath();
        }

        $files = Finder::create()
            ->files()
            ->name('*.html.twig')
            ->exclude('tests')
            ->in($searchpaths);
        foreach ($files as $file) {
            // @todo use DI once we stop using compound container.
            $relative = Path::makeRelative($file->getRealPath(), Drupal::getContainer()->getParameter('app.root'));
            // Loading the template ensures the compiled template is cached.
            try {
                $this->twig->load($relative);
            } catch (LoaderError | RuntimeError | SyntaxError $e) {
                $this->logger->error($e->getMessage());
                continue;
            }
            (new DrushStyle($input, $output))->success(sprintf('Compiled twig template %s', $relative));
        }
        return Command::SUCCESS;
    }
}
