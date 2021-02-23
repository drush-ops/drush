<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

class PhpCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    /**
     * Evaluate arbitrary php code after bootstrapping Drupal (if available).
     *
     * @command php:eval
     * @param $code PHP code. If shell escaping gets too tedious, consider using the php:script command.
     * @usage drush php:eval '$node = node_load(1); print $node->title;'
     *   Loads node with nid 1 and then prints its title.
     * @usage drush php:eval "file_unmanaged_copy(\'$HOME/Pictures/image.jpg\', \'public://image.jpg\');"
     *   Copies a file whose path is determined by an environment's variable. Use of double quotes so the variable $HOME gets replaced by its value.
     * @usage drush php:eval "node_access_rebuild();"
     *   Rebuild node access permissions.
     * @aliases eval,ev,php-eval
     * @bootstrap max
     */
    public function evaluate($code, $options = ['format' => 'var_export'])
    {
        return eval($code . ';');
    }

    /**
     * Run php a script after a full Drupal bootstrap.
     *
     * A useful alternative to eval command when your php is lengthy or you
     * can't be bothered to figure out bash quoting. If you plan to share a
     * script with others, consider making a full Drush command instead, since
     * that's more self-documenting.  Drush provides commandline options to the
     * script via a variable called <info>$extra</info>.
     *
     * @command php:script
     * @option script-path Additional paths to search for scripts, separated by
     *   : (Unix-based systems) or ; (Windows).
     * @usage drush php:script example --script-path=/path/to/scripts:/another/path
     *   Run a script named example.php from specified paths
     * @usage drush php:script -
     *   Run PHP code from standard input.
     * @usage drush php:script
     *   List all available scripts.
     * @usage drush php:script foo -- apple --cider
     *   Run foo.php script with argument <info>apple</info> and option <info>cider</info>. Note the
     *   <info>--</info> separator.
     * @aliases scr,php-script
     * @topics docs:script
     * @bootstrap max
     * @throws \Exception
     */
    public function script(array $extra, $options = ['format' => 'var_export', 'script-path' => self::REQ])
    {
        $found = false;
        $script = array_shift($extra);

        if ($script == '-') {
            return eval($this->stdin()->contents());
        } elseif (file_exists($script)) {
            $found = $script;
        } else {
            // Array of paths to search for scripts
            $searchpath['cwd'] = $this->getConfig()->cwd();

            // Additional script paths, specified by 'script-path' option
            if ($script_path = $options['script-path']) {
                foreach (explode(PATH_SEPARATOR, $script_path) as $path) {
                    $searchpath[] = $path;
                }
            }
            $this->logger()->debug(dt('Searching for scripts in ') . implode(',', $searchpath));

            if (empty($script)) {
                $all = [];
                // List all available scripts.
                $files = Finder::create()
                    ->files()
                    ->name('*.php')
                    ->depth(0)
                    ->in($searchpath);
                foreach ($files as $file) {
                    $all[] = $file->getRelativePathname();
                }
                return implode("\n", $all);
            } else {
                // Execute the specified script.
                foreach ($searchpath as $path) {
                    $script_filename = $path . '/' . $script;
                    if (file_exists($script_filename . '.php')) {
                        $script_filename .= '.php';
                    }
                    if (file_exists($script_filename)) {
                        $found = $script_filename;
                        break;
                    }
                    $all[] = $script_filename;
                }
                if (!$found) {
                    throw new \Exception(dt('Unable to find any of the following: @files', ['@files' => implode(', ', $all)]));
                }
            }
        }

        if ($found) {
            $return = include($found);
            // 1 just means success so don't return it.
            // http://us3.php.net/manual/en/function.include.php#example-120
            if ($return !== 1) {
                return $return;
            }
        }
    }
}
