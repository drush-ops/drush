<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class TwigCommands extends DrushCommands
{
    use AutowireTrait;

    const COMPILE = 'twig:compile';
    #[Deprecated('Use TwigUnusedCommand::UNUSED instead.')]
    const UNUSED = 'twig:unused';

    public function __construct(
        protected TwigEnvironment $twig,
        protected ModuleHandlerInterface $moduleHandler,
        private readonly ModuleExtensionList $extensionList,
    ) {
    }

    /**
     * Compile all Twig template(s).
     */
    #[CLI\Command(name: self::COMPILE, aliases: ['twigc', 'twig-compile'])]
    public function twigCompile(): void
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
            $relative = Path::makeRelative($file->getRealPath(), Drush::bootstrapManager()->getRoot());
            // Loading the template ensures the compiled template is cached.
            try {
                $this->twig->load($relative);
            } catch (LoaderError | RuntimeError | SyntaxError $e) {
                $this->logger()->error($e->getMessage());
                continue;
            }
            $this->logger()->success(dt('Compiled twig template !path', ['!path' => $relative]));
        }
    }
}
