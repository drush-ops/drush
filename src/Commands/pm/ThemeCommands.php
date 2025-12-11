<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

final class ThemeCommands extends DrushCommands
{
    use AutowireTrait;

    const INSTALL = 'theme:install';
    const UNINSTALL = 'theme:uninstall';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected ThemeInstallerInterface $themeInstaller,
        protected ModuleInstallerInterface $moduleInstaller,
        protected ThemeExtensionList $extensionListTheme,
    ) {
        parent::__construct();
    }

    public function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }

    public function getThemeInstaller(): ThemeInstallerInterface
    {
        return $this->themeInstaller;
    }

    public function getModuleInstaller(): ModuleInstallerInterface
    {
        return $this->moduleInstaller;
    }

    public function getExtensionListTheme(): ThemeExtensionList
    {
        return $this->extensionListTheme;
    }

    /**
     * Install one or more themes.
     */
    #[CLI\Command(name: self::INSTALL, aliases: ['thin', 'theme:enable', 'then', 'theme-enable'])]
    #[CLI\Argument(name: 'themes', description: 'A comma delimited list of themes.')]
    public function install(array $themes): void
    {
        $themes = StringUtils::csvToArray($themes);
        $todo = $this->addInstallDependencies($themes);
        $todo_str = ['!list' => implode(', ', $todo)];
        if (!empty($todo)) {
            $this->output()->writeln(dt('The following module(s) and themes(s) will be installed: !list', $todo_str));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }

            $modules = array_diff(array_values($todo), array_values($themes));
            if (!empty($modules)) {
                if (!$this->getModuleInstaller()->install($modules, true)) {
                    throw new \Exception('Unable to install modules.');
                }
            }
        }

        if (!$this->getThemeInstaller()->install($themes, true)) {
            throw new \Exception('Unable to install themes.');
        }
        $this->logger()->success(dt('Successfully installed theme: !list', ['!list' => implode(', ', $themes)]));
    }

    /**
     * Uninstall themes.
     */
    #[CLI\Command(name: self::UNINSTALL, aliases: ['theme:un', 'thun', 'theme-uninstall'])]
    #[CLI\Argument(name: 'themes', description: 'A comma delimited list of themes.')]
    public function uninstall(array $themes): void
    {
        $themes = StringUtils::csvToArray($themes);
        // The uninstall() method has no return value. Assume it succeeded, and
        // allow exceptions to bubble.
        $this->getThemeInstaller()->uninstall($themes);
        $this->logger()->success(dt('Successfully uninstalled theme: !list', ['!list' => implode(', ', $themes)]));
    }

    /**
     * Returns a list of modules and themes to be installed.
     *
     * @param array $themes
     *   List of themes to install
     *
     * @return array
     *   List of themes and modules that need to be installed.
     */
    public function addInstallDependencies($themes): array
    {
        $theme_data = $this->getExtensionListTheme()->reset()->getList();
        $theme_list  = array_combine($themes, $themes);
        if ($missing_themes = array_diff_key($theme_list, $theme_data)) {
            // One or more of the given themes doesn't exist.
            throw new MissingDependencyException(sprintf('Unable to install themes %s due to missing themes %s.', implode(', ', $theme_list), implode(', ', $missing_themes)));
        }
        $extension_config = $this->getConfigFactory()->getEditable('core.extension');
        $installed_modules = $extension_config->get('module') ?: [];

        // Copied from \Drupal\Core\Extension\ModuleInstaller::install
        // Add dependencies to the list. The new modules will be processed as
        // the while loop continues.
        foreach (array_keys($theme_list) as $theme) {
            $modules = $theme_data[$theme]->module_dependencies;
            foreach (array_keys($modules) as $dependency) {
                // Skip already installed modules.
                if (!isset($theme_list[$dependency]) && !isset($installed_modules[$dependency])) {
                    $theme_list[$dependency] = $dependency;
                }
            }
        }

        // Remove already installed modules.
        $todo = array_diff_key($theme_list, $installed_modules);
        return $todo;
    }
}
