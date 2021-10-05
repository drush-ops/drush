<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Commands\DrushCommands;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;

class TwigCommands extends DrushCommands
{
  /**
   * @var \Drupal\Core\Template\TwigEnvironment
   */
    protected $twig;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
    protected $moduleHandler;

  /**
   * @return \Drupal\Core\Template\TwigEnvironment
   */
    public function getTwig()
    {
        return $this->twig;
    }

  /**
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

  /**
   * @param \Drupal\Core\Template\TwigEnvironment $twig
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
     *
     * @param $searchpaths A comma delimited list of paths to recursively search
     * @usage drush twig:unused --field=template /var/www/mass.local/docroot/modules/custom,/var/www/mass.local/docroot/themes/custom
     *   Output a simple list of potentially unused templates.
     * @table-style default
     * @field-labels
     *   template: Template
     *   compiled: Compiled
     * @default-fields template,compiled
     * @filter-output
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *
     * @command twig:unused
     */
    public function unused($searchpaths)
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
   *
   * @command twig:compile
   * @aliases twigc,twig-compile
   */
    public function twigCompile()
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
