<?php
namespace Drush\Commands\core;

use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Log\LogLevel;
use Robo\LoadAllTasks;
use Robo\Contract\IOAwareInterface;
use Robo\Contract\BuilderAwareInterface;

class InitCommands extends DrushCommands implements BuilderAwareInterface, IOAwareInterface
{
    use LoadAllTasks;

    /**
     * Enrich the bash startup file with bash aliases and a smart command prompt.
     *
     * @command core:init
     *
     * @option $edit Open the new config file in an editor.
     * @option $add-path Always add Drush to the $PATH in the user's .bashrc
     *   file, even if it is already in the $PATH. Use --no-add-path to skip
     *   updating .bashrc with the Drush $PATH. Default is to update .bashrc
     *   only if Drush is not already in the $PATH.
     * @optionset_get_editor
     * @aliases init,core-init
     * @usage core-init --edit
     *   Enrich Bash and open drush config file in editor.
     * @usage core-init --edit --bg
     *   Return to shell prompt as soon as the editor window opens
     */
    public function initializeDrush($options = ['edit' => false, 'add-path' => ''])
    {
        $home = Drush::config()->get('env.home');
        $drush_config_dir = $home . "/.drush";
        // @todo copy a config.yml.
        $drush_config_file = $drush_config_dir . "/drushrc.php";
        $drush_bashrc = $drush_config_dir . "/drush.bashrc";
        $drush_prompt = $drush_config_dir . "/drush.prompt.sh";
        $examples_dir = DRUSH_BASE_PATH . "/examples";
        $example_configuration = $examples_dir . "/example.drushrc.php";
        $example_bashrc = $examples_dir . "/example.bashrc";
        $example_prompt = $examples_dir . "/example.prompt.sh";

        $collection = $this->collectionBuilder();

        // Create a ~/.drush directory if it does not yet exist
        $collection->taskFilesystemStack()->mkdir($drush_config_dir);

        // If there is no ~/.drush/drushrc.php, then copy the
        // example Drush configuration file here
        if (!is_file($drush_config_file)) {
            $collection->taskWriteToFile($drush_config_file)->textFromFile($example_configuration);
        }

        // Decide whether we want to add our Bash commands to
        // ~/.bashrc or ~/.bash_profile, and create a task to
        // update it with includes of the various files we write,
        // as needed.  If it is, then we will add it to the collection.
        $bashrc = $this->findBashrc($home);
        $taskUpdateBashrc = $this->taskWriteToFile($bashrc)->append();

        // List of Drush bash configuration files, and
        // their source templates.
        $drushBashFiles = [
        $drush_bashrc => $example_bashrc,
        $drush_prompt => $example_prompt,
        ];

        // Mapping from Drush bash configuration files
        // to a description of what each one is.
        $drushBashFileDescriptions = [
        $drush_bashrc => 'Drush bash customizations',
        $drush_prompt => 'Drush prompt customizations',
        ];

        foreach ($drushBashFiles as $destFile => $sourceFile) {
            // If the destination file does not exist, then
            // copy the example file there.
            if (!is_file($destFile)) {
                $collection->taskWriteToFile($destFile)->textFromFile($sourceFile);
                $description = $drushBashFileDescriptions[$destFile];
                $collection->progressMessage('Copied {description} to {path}', ['description' => $description, 'path' => $destFile], LogLevel::OK);
                $pattern = basename($destFile);
                $taskUpdateBashrc->appendUnlessMatches("#$pattern#", "\n# Include $description.". $this->bashAddition($destFile));
            }
        }

        // If Drush is not in the $PATH, then figure out which
        // path to add so that Drush can be found globally.
        $add_path = $options['add-path'];
        if ((!drush_which("drush") || $add_path) && ($add_path !== false)) {
            $drush_path = $this->findPathToDrush();
            $drush_path = preg_replace("%^" . preg_quote($home) . "/%", '$HOME/', $drush_path);
            $pattern = "$drush_path";
            $taskUpdateBashrc->appendUnlessMatches("#$pattern#", "\n# Path to Drush, added by 'drush init'.\nexport PATH=\"\$PATH:$drush_path\"\n\n");
        }

        $openEditor = false;
        if ($taskUpdateBashrc->wouldChange()) {
            if ($this->io()->confirm(dt("Modify !file to include Drush configuration files?", array('!file' => $bashrc)))) {
                $collection->addTask($taskUpdateBashrc);
                $collection->progressMessage('Updated bash configuration file {path}', ['path' => $bashrc], LogLevel::OK);
                $collection->progressMessage('Start a new shell in order to experience the improvements (e.g. `{shell}`).', ['shell' => 'bash'], LogLevel::OK);
                $openEditor = $options['edit'];
            } else {
                throw new UserAbortException();
            }
        } else {
            $collection->progressMessage('No code added to {path}', ['path' => $bashrc]);
        }
        $result = $collection->run();

        if ($result->wasSuccessful() && $openEditor) {
            $exec = drush_get_editor();
            drush_shell_exec_interactive($exec, $drush_config_file, $drush_config_file);
        }

        return $result;
    }

    /**
     * Determine which .bashrc file is best to use on this platform.
     */
    protected function findBashrc($home)
    {
        return $home . "/.bashrc";
    }

    /**
     * Determine where Drush is located, so that we can add
     * that location to the $PATH.
     */
    protected function findPathToDrush()
    {
        // First test: is Drush inside a vendor directory?
        // Does vendor/bin exist?  If so, use that.  We do
        // not have a good way to locate the 'bin' directory
        // if it has been relocated in the composer.json config
        // section.
        if ($vendor_pos = strpos(DRUSH_BASE_PATH, "/vendor/")) {
            $vendor_dir = substr(DRUSH_BASE_PATH, 0, $vendor_pos + 7);
            $vendor_bin = $vendor_dir . '/bin';
            if (is_dir($vendor_bin)) {
                return $vendor_bin;
            }
        }

        // Fallback is to use the directory that Drush is in.
        return DRUSH_BASE_PATH;
    }

    protected function bashAddition($file)
    {
        return <<<EOD

if [ -f "$file" ] ; then
  source $file
fi

EOD;
    }
}
