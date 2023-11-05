<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteProcess\Util\Escape;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Exec\ExecTrait;

final class EditCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;
    use ExecTrait;

    const EDIT = 'core:edit';

    /**
     * Edit drush.yml, site alias, and Drupal settings.php files.
     */
    #[CLI\Command(name: self::EDIT, aliases: ['conf', 'config', 'core-edit'])]
    #[CLI\Argument(name: 'filter', description: 'A substring for filtering the list of files. Omit this argument to choose from loaded files.')]
    #[CLI\Usage(name: 'drush core:edit', description: 'Pick from a list of config/alias/settings files. Open selected in editor.')]
    #[CLI\Usage(name: 'drush --bg core-config', description: 'Return to shell prompt as soon as the editor window opens.')]
    #[CLI\Usage(name: 'drush core:edit etc', description: 'Edit the global configuration file.')]
    #[CLI\Usage(name: 'drush core:edit demo.alia', description: 'Edit a particular alias file.')]
    #[CLI\Usage(name: 'drush core:edit sett', description: 'Edit settings.php for the current Drupal site.')]
    #[CLI\Usage(name: 'drush core:edit --choice=2', description: 'Edit the second file in the choice list.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    #[CLI\OptionsetGetEditor]
    public function edit($filter = null, array $options = []): void
    {
        $all = $this->load();

        // Apply any filter that was supplied.
        if ($filter) {
            foreach ($all as $file => $display) {
                if (!str_contains($file, $filter)) {
                    unset($all[$file]);
                }
            }
        }

        $editor = self::getEditor($options['editor']);
        if (count($all) == 1) {
            $filepath = current($all);
        } else {
            $choice = $this->io()->choice(dt("Choose a file to edit"), $all);
            $filepath = $choice;
            // We don't yet support launching editor at a start line.
            if ($pos = strpos($filepath, ':')) {
                $filepath = substr($filepath, 0, $pos);
            }
        }

        // A bit awkward due to backward compat.
        $cmd = sprintf($editor, Escape::shellArg($filepath));
        $process = $this->processManager()->shell($cmd);
        $process->setTty(true);
        $process->mustRun();
    }

    public function load($headers = true): array
    {
        $php_header = $rcs_header = $aliases_header = $drupal_header = $drupal = [];
        $php = $this->phpIniFiles();
        if (!empty($php)) {
            if ($headers) {
                $php_header = ['phpini' => '-- PHP ini files --'];
            }
        }

        $bash = $this->bashFiles();
        if (!empty($bash)) {
            if ($headers) {
                $bash_header = ['bash' => '-- Bash files --'];
            }
        }

        if ($rcs = $this->getConfig()->configPaths()) {
            // @todo filter out any files that are within Drush.
            $rcs = array_combine($rcs, $rcs);
            if ($headers) {
                $rcs_header = ['drushyml' => '-- drush.yml --'];
            }
        }

        if ($aliases = $this->siteAliasManager()->listAllFilePaths()) {
            sort($aliases);
            $aliases = array_combine($aliases, $aliases);
            if ($headers) {
                $aliases_header = ['aliases' => '-- Aliases --'];
            }
        }

        $bootstrapManager = Drush::bootstrapManager();
        if ($bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL)) {
            $boot = $bootstrapManager->bootstrap();
            $site_root = $boot->getKernel()->getSitePath();
            $path = realpath($site_root . '/settings.php');
            $drupal[$path] = $path;
            if (file_exists($site_root . '/settings.local.php')) {
                $path = realpath($site_root . '/settings.local.php');
                $drupal[$path] = $path;
            }
            if ($path = realpath($bootstrapManager->getRoot() . '/.htaccess')) {
                $drupal[$path] = $path;
            }
            if ($headers) {
                $drupal_header = ['drupal' => '-- Drupal --'];
            }
        }

        return array_merge($php_header, $php, $bash_header, $bash, $rcs_header, $rcs, $aliases_header, $aliases, $drupal_header, $drupal);
    }

    public static function phpIniFiles(): array
    {
        $return = [];
        if ($file = php_ini_loaded_file()) {
            $return = [$file];
        }
        return $return;
    }

    public function bashFiles(): array
    {
        $bashFiles = [];
        $home = $this->getConfig()->home();
        if ($bashrc = self::findBashrc($home)) {
            $bashFiles[$bashrc] = $bashrc;
        }
        return $bashFiles;
    }

    /**
     * Determine which .bashrc file is best to use on this platform.
     *
     * TODO: Also exists as InitCommands::findBashrc. Decide on class-based
     * way to share code like this.
     */
    public static function findBashrc($home): string
    {
        return $home . "/.bashrc";
    }

    public function complete(): array
    {
        return ['values' => $this->load(false)];
    }
}
