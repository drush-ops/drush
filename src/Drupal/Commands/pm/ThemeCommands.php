<?php
namespace Drush\Drupal\Commands\pm;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Utils\StringUtils;

class ThemeCommands extends DrushCommands
{

    protected $themeInstaller;

    public function __construct(ThemeInstallerInterface $themeInstaller)
    {
        parent::__construct();
        $this->themeInstaller = $themeInstaller;
    }

    /**
     * @return mixed
     */
    public function getThemeInstaller()
    {
        return $this->themeInstaller;
    }

    /**
     * Enable one or more themes.
     *
     * @command theme:enable
     * @param $themes A comma delimited list of themes.
     * @aliases then,theme-enable
     */
    public function enable(array $themes)
    {
        $themes = StringUtils::csvToArray($themes);
        if (!$this->getThemeInstaller()->install($themes, true)) {
            throw new \Exception('Unable to install themes.');
        }
        $this->logger()->success(dt('Successfully enabled theme: !list', ['!list' => implode(', ', $themes)]));
    }

    /**
     * Uninstall theme.
     *
     * @command theme:uninstall
     * @param $themes A comma delimited list of themes.
     * @aliases thun,theme-uninstall
     */
    public function uninstall(array $themes)
    {
        $themes = StringUtils::csvToArray($themes);
        // The uninstall() method has no return value. Assume it succeeded, and
        // allow exceptions to bubble.
        $this->getThemeInstaller()->uninstall($themes, true);
        $this->logger()->success(dt('Successfully uninstalled theme: !list', ['!list' => implode(', ', $themes)]));
    }
}
