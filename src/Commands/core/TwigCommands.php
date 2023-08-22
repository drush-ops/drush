<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TwigCommands extends DrushCommands
{
    const UNUSED = 'twig:unused';
    const COMPILE = 'twig:compile';
    const DEBUG = 'twig:debug';

    public function __construct(protected TwigEnvironment $twig, protected ModuleHandlerInterface $moduleHandler, private ExtensionList $extensionList, private StateInterface $state, private DrupalKernelInterface $kernel)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('twig'),
            $container->get('module_handler'),
            $container->get('extension.list.module'),
            $container->get('state'),
            $container->get('kernel'),
        );

        return $commandHandler;
    }

    public function getTwig(): TwigEnvironment
    {
        return $this->twig;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
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
            $this->getTwig()->load($relative);
            $this->logger()->success(dt('Compiled twig template !path', ['!path' => $relative]));
        }
    }

    /**
     * Enables Twig debug and disables caching Twig templates.
     *
     * @see \Drupal\system\Form\DevelopmentSettingsForm::submitForm()
     */
    #[CLI\Command(name: self::DEBUG, aliases: ['twig-debug'])]
    #[CLI\Argument(name: 'mode', description: 'Debug mode. Recognized values: <info>on</info>, <info>off</info>.', suggestedValues: ['on', 'off'])]
    #[CLI\Version(version: '12.1')]
    public function twigDebug(string $mode): void
    {
        // @todo Remove this condition once Drush drops support for Drupal 10.0.
        if (version_compare(\Drupal::VERSION, '10.1.0') < 0) {
            throw new \Exception('Twig debug command requires Drupal 10.1.0 and above.');
        }
        $mode = match ($mode) {
            'on' => true,
            'off' => false,
            default => throw new \Exception('Twig debug mode must be either "on" or "off".'),
        };
        $twig_development = [
            'twig_debug' => $mode,
            'twig_cache_disable' => $mode,
        ];
        $this->state->setMultiple($twig_development);
        $this->kernel->invalidateContainer();
        $this->io()->success(
            dt('{operation} twig debug.', ['operation' => $mode ? 'Enabled' : 'Disabled']),
        );
    }
}
