<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Drush;

class EditCommands extends DrushCommands
{

    /**
     * Edit drushrc, site alias, and Drupal settings.php files.
     *
     * @command core-edit
     * @bootstrap DRUSH_BOOTSTRAP_MAX
     * @param $filter A substring for filtering the list of files. Omit this argument to choose from loaded files.
     * @optionset_get_editor
     * @usage drush core-config
     *   Pick from a list of config/alias/settings files. Open selected in editor.
     * @usage drush --bg core-config
     *   Return to shell prompt as soon as the editor window opens.
     * @usage drush core-config etc
     *   Edit the global configuration file.
     * @usage drush core-config demo.alia
     * Edit a particular alias file.
     * @usage drush core-config sett
     *   Edit settings.php for the current Drupal site.
     * @usage drush core-config --choice=2
     *  Edit the second file in the choice list.
     * @aliases conf, config
     * @complete \Drush\Commands\core\EditCommands::complete
     */
    public function edit($filter = null)
    {
        $all = $this->load();

        // Apply any filter that was supplied.
        if ($filter) {
            foreach ($all as $file => $display) {
                if (strpos($file, $filter) === false) {
                    unset($all[$file]);
                }
            }
        }

        $exec = drush_get_editor();
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
        return drush_shell_exec_interactive($exec, $filepath, $filepath);
    }

    public function load($headers = true)
    {
        $php_header = $php = $rcs_header = $rcs = $aliases_header = $aliases = $drupal_header = $drupal = array();
        $php = $this->phpIniFiles();
        if (!empty($php)) {
            if ($headers) {
                $php_header = array('phpini' => '-- PHP ini files --');
            }
        }

        $bash = $this->bashFiles();
        if (!empty($bash)) {
            if ($headers) {
                $bash_header = array('bash' => '-- Bash files --');
            }
        }

        drush_sitealias_load_all();
        if ($rcs = drush_get_context_options('context-path', true)) {
            if ($headers) {
                $rcs_header = array('drushrc' => '-- Drushrc --');
            }
        }
        if ($aliases = drush_get_context('drush-alias-files')) {
            $aliases = drush_map_assoc($aliases);
            if ($headers) {
                $aliases_header = array('aliases' => '-- Aliases --');
            }
        }
        if ($site_root = Drush::bootstrap()->confPath()) {
            $path = realpath($site_root . '/settings.php');
            $drupal[$path] = $path;
            if (file_exists($site_root . '/settings.local.php')) {
                $path = realpath($site_root . '/settings.local.php');
                $drupal[$path] = $path;
            }
            $path = realpath(DRUPAL_ROOT . '/.htaccess');
            $drupal[$path] = $path;
            if ($headers) {
                $drupal_header = array('drupal' => '-- Drupal --');
            }
        }
        $commands = drush_get_commands();
        ksort($commands);
        $commandfiles_header = array('commands' => '-- Commands --');
        foreach ($commands as $command) {
            $acc = $command['annotated-command-callback'];
            $reflection = $acc ? new \ReflectionMethod($acc[0], $acc[1]) : new \ReflectionFunction($command['callback']);
            $commandfiles[$reflection->getFileName() . ':' . $reflection->getStartLine()] = $command['command'];
        }
        return array_merge($php_header, $php, $bash_header, $bash, $rcs_header, $rcs, $aliases_header, $aliases, $commandfiles_header, $commandfiles, $drupal_header, $drupal);
    }

    public static function phpIniFiles()
    {
        $ini_files = array();
        $path = php_ini_loaded_file();
        $ini_files[$path] = $path;
        if ($drush_ini = getenv('DRUSH_INI')) {
            if (file_exists($drush_ini)) {
                $ini_files[$drush_ini] = $drush_ini;
            }
        }
        foreach (array(DRUSH_BASE_PATH, '/etc/drush', drush_server_home() . '/.drush') as $ini_dir) {
            if (file_exists($ini_dir . "/drush.ini")) {
                $path = realpath($ini_dir . "/drush.ini");
                $ini_files[$path] = $path;
            }
        }
        return $ini_files;
    }

    public static function bashFiles()
    {
        $bashFiles = array();
        $home = drush_server_home();
        if ($bashrc = self::findBashrc($home)) {
            $bashFiles[$bashrc] = $bashrc;
        }
        $prompt = $home . '/.drush/drush.prompt.sh';
        if (file_exists($prompt)) {
            $bashFiles[$prompt] = $prompt;
        }
        return $bashFiles;
    }

    /**
     * Determine which .bashrc file is best to use on this platform.
     *
     * TODO: Also exists as InitCommands::findBashrc. Decide on class-based
     * way to share code like this.
     */
    public function findBashrc($home)
    {
        return $home . "/.bashrc";
    }

    public function complete()
    {
        return array('values' => $this->load(false));
    }
}
