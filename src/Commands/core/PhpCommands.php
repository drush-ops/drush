<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

final class PhpCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    const SCRIPT = 'php:script';
    const EVAL = 'php:eval';

    /**
     * Evaluate arbitrary php code after bootstrapping Drupal (if available).
     */
    #[CLI\Command(name: self::EVAL, aliases: ['eval', 'ev', 'php-eval'])]
    #[CLI\Argument(name: 'code', description: 'PHP code. If shell escaping gets too tedious, consider using the php:script command.')]
    #[CLI\Usage(name: "drush php:eval '\$node = \Drupal\node\Entity\Node::load(1); print \$node->getTitle();'", description: 'Loads node with nid 1 and then prints its title.')]
    #[CLI\Usage(name: 'drush php:eval "\Drupal::service(\'file_system\')->copy(\'$HOME/Pictures/image.jpg\', \'public://image.jpg\');"', description: 'Copies a file whose path is determined by an environment\'s variable. Use of double quotes so the variable $HOME gets replaced by its value.')]
    #[CLI\Usage(name: 'drush php:eval "node_access_rebuild();"', description: 'Rebuild node access permissions.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
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
     */
    #[CLI\Command(name: self::SCRIPT, aliases: ['scr', 'php-script'])]
    #[CLI\Option(name: 'script-path', description: 'Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).')]
    #[CLI\Usage(name: 'drush php:script example --script-path=/path/to/scripts:/another/path', description: 'Run a script named example.php from specified paths')]
    #[CLI\Usage(name: 'drush php:script -', description: 'Run PHP code from standard input.')]
    #[CLI\Usage(name: 'drush php:script', description: 'List all available scripts.')]
    #[CLI\Usage(name: 'drush php:script foo -- apple --cider', description: 'Run foo.php script with argument <info>apple</info> and option <info>cider</info>. Note the <info>--</info> separator.')]
    #[CLI\Topics(topics: [DocsCommands::SCRIPT])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
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
