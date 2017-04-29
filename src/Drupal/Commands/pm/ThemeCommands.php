<?php
namespace Drush\Drupal\Commands\pm;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drush\Commands\DrushCommands;

class ThemeCommands extends DrushCommands {

  protected $themeInstaller;

  public function __construct(ThemeInstallerInterface $themeInstaller) {
    parent::__construct();
    $this->themeInstaller = $themeInstaller;
  }

  /**
   * @return mixed
   */
  public function getThemeInstaller() {
    return $this->themeInstaller;
  }

  /**
   * Enable one or more themes.
   *
   * @command theme-enable
   * @param $themes A comma delimited list of themes.
   * @aliases then
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function enable($themes) {
    $themes = _convert_csv_to_array($themes);
    if (!$this->getThemeInstaller()->install($themes, TRUE)) {
      throw new \Exception('Unable to install themes.');
    }
    $this->logger()->success(dt('Successfully enabled theme: !list', ['!list' => implode(', ', $themes)]));
  }

  /**
   * Uninstall theme.
   *
   * @command theme-uninstall
   * @param $themes A comma delimited list of themes.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases thun
   */
  public function uninstall($themes) {
    $themes = _convert_csv_to_array($themes);
    if (!$this->getThemeInstaller()->uninstall($themes, TRUE)) {
      throw new \Exception('Unable to uninstall themes.');
    }
    $this->logger()->success(dt('Successfully uninstalled theme: !list', ['!list' => implode(', ', $themes)]));
    // Our logger got blown away during the container rebuild above.
    $boot = \Drush::bootstrapManager()->bootstrap();
    $boot->add_logger();
  }

}
