<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\HelpCLIFormatter;
use Drush\Commands\help\ListCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

class MkCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{

    use SiteAliasManagerAwareTrait;

    /**
     * Build an mkdocs site.
     *
     * @option destination The target directory where files should be written. A relative path starts at Drupal root.
     *
     * @command mk:docs
     * @bootstrap max
     * @hidden
     * @usage drush mk:docs --destination=/tmp/build
     *   Build an mkdocs site at /tmp/build directory.
     */
    public function docs($options = ['destination' => self::REQ])
    {
        $this->prepare($options['destination']);

        $application = Drush::getApplication();
        $all = $application->all();
        $namespaced = ListCommands::categorize($all);

        // Write content files
        $pages = $nav = [];
        foreach ($namespaced as $category => $commands) {
            foreach ($commands as $command) {
                if ($command instanceof AnnotatedCommand) {
                    $command->optionsHook();
                }
                $name = $command->getName();
                $filename = str_replace(':', '_', $name)  . '.md';
                $pages[] = $filename;
                $body = "# $name\n\n";
                if ($command->getDescription()) {
                    $body .= $command->getDescription() ."\n\n";
                    if ($command->getHelp()) {
                        $body .= $command->getHelp(). "\n\n";
                    }
                }
                if ($examples = $command->getExampleUsages()) {
                    $body .= "#### Examples\n\n";
                    foreach ($examples as $key => $value) {
                        $body .= '- <code>' . $key . '</code>. ' . $value . "\n";
                    }
                    $body .= "\n";
                }
                if ($args = $command->getDefinition()->getArguments()) {
                    $body .= "#### Arguments\n\n";
                    foreach ($args as $arg) {
                        $arg_array = self::argToArray($arg);
                        $body .= '- **' . HelpCLIFormatter::formatArgumentName($arg_array) . '**. ' . $arg->getDescription() . "\n";
                    }
                    $body .= "\n";
                }
                if ($opts = $command->getDefinition()->getOptions()) {
                    $body .= "#### Options\n\n";
                    foreach ($opts as $opt) {
                        if (!HelpCLIFormatter::isGlobalOption($opt->getName())) {
                            $opt_array = self::optionToArray($opt);
                            $body .= '- **' . HelpCLIFormatter::formatOptionKeys($opt_array) . '**. ' . HelpCLIFormatter::formatOptionDescription($opt_array) . "\n";
                        }
                    }
                    $body .= "\n";
                }
                if ($topics = $command->getTopics()) {
                    $body .= "#### Topics\n\n";
                    foreach ($topics as $value) {
                        $body .= "- `drush $value`\n";
                    }
                    $body .= "\n";
                }
                if ($aliases = $command->getAliases()) {
                    $body .= "#### Aliases\n\n";
                    foreach ($aliases as $value) {
                        $body .= '- ' . $value . "\n";
                    }
                    $body .= "\n";
                }
                $body .= '!!! note "Legend"' . "\n" . <<<EOT
    - An argument or option with square brackets is optional.
    - Any default value is listed at end of arg/option description.
    - An ellipsis indicates that an argument accepts multiple values separated by a space.
EOT;
                file_put_contents(Path::join($options['destination'], 'docs', $filename), $body);
            }
            $this->logger()->info('Found {pages} pages in {cat}', ['pages' => count($pages), 'cat' => $category]);
            $nav[] = [$category => $pages];
            unset($pages);
        }

        $this->writeyml($nav, $options['destination']);
    }

    /**
     * Write mkdocs.yml.
     *
     * @param $nav
     * @param $dest
     */
    protected function writeyml($nav, $dest)
    {
        // Write yml file.
        $mkdocs = [
            'site_name' => 'Drush Commands',
            'site_author' => 'Moshe Weitzman',
            'repo_name' => 'GitHub',
            'repo_url' => 'https://github.com/drush-ops/drush',
            'edit_uri' => '',
            'theme' => [
                'name' => 'readthedocs',
            ],
            'site_url' => 'http://commands.drush.org',
            'markdown_extensions' => [
                ['toc' => [
                    'toc_depth' => 0,
                    'permalink' => true,
                ]],
                ['admonition' => []],
            ],
            'nav' => $nav,
        ];
        $yaml = Yaml::dump($mkdocs, PHP_INT_MAX, 2);
        file_put_contents(Path::join($dest, 'mkdocs.yml'), $yaml);
    }

    /**
     * Empty target directories.
     *
     * @param $destination
     */
    protected function prepare($destination)
    {
        $fs = new Filesystem();
        $dest = $destination;
        if ($fs->exists($dest)) {
            drush_delete_dir_contents($dest);
        }
        $fs->mkdir($dest);
        $docs_dir = Path::join($dest, 'docs');
        $fs->mkdir($docs_dir);
        $favicon_dir = Path::join($dest, 'docs', 'img');
        $fs->mkdir($favicon_dir);
        $fs->copy('../misc/favicon.ico', Path::join($favicon_dir, 'favicon.ico'));
        $fs->copy('../docs/index.md', Path::join($docs_dir, 'index.md'));
        // $fs->copy('../drush_logo-black.png', Path::join($docs_dir, 'logo.png'));
    }

    /**
     * Build an array since thats what HelpCLIFormatter expects.
     *
     * @param \Symfony\Component\Console\Input\InputArgument $arg
     *
     * @return array
     */
    public static function argToArray(InputArgument $arg)
    {
        $return = [
            'name' => '--' . $arg->getName(),
            'is_array' => $arg->isArray(),
            'is_required' => $arg->isRequired(),
        ];
        return $return;
    }

    /**
     * Build an array since thats what HelpCLIFormatter expects.
     *
     * @param \Symfony\Component\Console\Input\InputOption $opt
     *
     * @return array
     */
    public static function optionToArray(InputOption $opt)
    {
        $return = [
            'name' => '--' . $opt->getName(),
            'accept_value' => $opt->acceptValue(),
            'is_value_required' => $opt->isValueRequired(),
            'shortcut' => $opt->getShortcut(),
            'description' => $opt->getDescription(),
        ];
        if ($opt->getDefault()) {
            $return['defaults'] = (array)$opt->getDefault();
        }
        return $return;
    }
}
