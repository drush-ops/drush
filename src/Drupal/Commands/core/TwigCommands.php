<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class TwigCommands extends DrushCommands
{
    const UNUSED = 'twig:unused';
    const COMPILE = 'twig:compile';
    /**
     * @var TwigEnvironment
     */
    protected $twig;

  /**
     * @var ModuleHandlerInterface
     */
    protected $moduleHandler;

    public function getTwig(): TwigEnvironment
    {
        return $this->twig;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

  /**
     * @param TwigEnvironment $twig
     * @param ModuleHandlerInterface $moduleHandler
     */
    public function __construct(TwigEnvironment $twig, ModuleHandlerInterface $moduleHandler)
    {
        $this->twig = $twig;
        $this->moduleHandler = $moduleHandler;
    }

  /**
     * Find potentially unused Twig templates.
     *
     * Immediately before running this command, web crawl your entire web site. Or
     * use your Production PHPStorage dir for comparison.
     */
    #[CLI\Command(name: self::UNUSED, aliases: [])]
    #[CLI\Argument(name: 'searchpaths', description: 'A comma delimited list of paths to recursively search')]
    #[CLI\Usage(name: 'drush twig:unused --field=template /var/www/mass.local/docroot/modules/custom,/var/www/mass.local/docroot/themes/custom', description: 'Output a simple list of potentially unused templates.')]
    #[CLI\FieldLabels(labels: ['template' => 'Template', 'compiled' => 'Compiled'])]
    #[CLI\DefaultTableFields(fields: ['template', 'compiled'])]
    public function unused($searchpaths): RowsOfFields
    {
        $unused = [];
        $phpstorage = PhpStorageFactory::get('twig');

        // Find all templates in the codebase.
        $files = Finder::create()
            ->files()
            ->name('*.html.twig')
            ->exclude('tests')
            ->in(StringUtils::csvToArray($searchpaths));
        $this->logger()->notice(dt('Found !count templates', ['!count' => count($files)]));

        // Check to see if a compiled equivalent exists in PHPStorage
        foreach ($files as $file) {
            $relative = Path::makeRelative($file->getRealPath(), Drush::bootstrapManager()->getRoot());
            $mainCls = $this->getTwig()->getTemplateClass($relative);
            $key = $this->getTwig()->getCache()->generateKey($relative, $mainCls);
            if (!$phpstorage->exists($key)) {
                $unused[$key] = [
                    'template' => $relative,
                    'compiled' => $key,
                ];
            }
        }
        $this->logger()->notice(dt('Found !count unused', ['!count' => count($unused)]));
        return new RowsOfFields($unused);
    }

  /**
   * Compile all Twig template(s).
   */
    #[CLI\Command(name: self::COMPILE, aliases: ['twigc', 'twig-compile'])]
    public function twigCompile(): void
    {
        require_once DRUSH_DRUPAL_CORE . "/themes/engines/twig/twig.engine";
        // Scan all enabled modules and themes.
        $modules = array_keys($this->getModuleHandler()->getModuleList());
        foreach ($modules as $module) {
            $searchpaths[] = drupal_get_path('module', $module);
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
            $this->getTwig()->loadTemplate($relative);
            $this->logger()->success(dt('Compiled twig template !path', ['!path' => $relative]));
        }
    }
}
