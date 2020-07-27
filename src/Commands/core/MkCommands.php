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
                $body = self::appendPreamble($command);
                $body .= self::appendUsages($command);
                $body .= self::appendArguments($command);
                $body .= self::appendOptions($command);
                if ($command instanceof AnnotatedCommand) {
                    $body .= self::appendTopics($command);
                }
                $body .= self::appendAliases($command);
                $body .= self::appendPostAmble();
                $filename = str_replace(':', '_', $command->getName())  . '.md';
                $pages[] = $filename;
                file_put_contents(Path::join($options['destination'], 'docs', $filename), $body);
            }
            $this->logger()->info('Found {pages} pages in {cat}', ['pages' => count($pages), 'cat' => $category]);
            $nav[] = [$category => $pages];
            unset($pages);
        }

        $this->writeyml($nav, $options['destination']);
    }

    protected static function appendPostAmble(): string
    {
        return '!!! note "Legend"' . "\n" . <<<EOT
    - An argument or option with square brackets is optional.
    - Any default value is listed at end of arg/option description.
    - An ellipsis indicates that an argument accepts multiple values separated by a space.
EOT;
    }

    protected static function appendAliases(AnnotatedCommand $command): string
    {
        if ($aliases = $command->getAliases()) {
            $body = "#### Aliases\n\n";
            foreach ($aliases as $value) {
                $body .= '- ' . $value . "\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendTopics(AnnotatedCommand $command): string
    {
        if ($topics = $command->getTopics()) {
            $body = "#### Topics\n\n";
            foreach ($topics as $name) {
                $value = "- `drush $name`\n";
                $topic_command = Drush::getApplication()->find($name);
                $topic_description = $topic_command->getDescription();
                if ($docs_relative = $topic_command->getAnnotationData()->get('topic')) {
                    $abs = Path::makeAbsolute($docs_relative, dirname($topic_command->getAnnotationData()->get('_path')));
                    if (file_exists($abs)) {
                        $docs_path = Path::join(DRUSH_BASE_PATH, 'docs');
                        if (Path::isBasePath($docs_path, $abs)) {
                            $rel_from_docs = str_replace('.md', '', Path::makeRelative($abs, $docs_path));
                            $value = "- [$topic_description](https://docs.drush.org/en/master/$rel_from_docs) ($name)";
                        } else {
                            $rel_from_root = Path::makeRelative($abs, DRUSH_BASE_PATH);
                            $value = "- [$topic_description](https://raw.githubusercontent.com/drush-ops/drush/master/$rel_from_root) ($name)";
                        }
                    }
                }
                $body .= "$value\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendOptions(AnnotatedCommand $command): string
    {
        if ($opts = $command->getDefinition()->getOptions()) {
            $body = '';
            foreach ($opts as $opt) {
                if (!HelpCLIFormatter::isGlobalOption($opt->getName())) {
                    $opt_array = self::optionToArray($opt);
                    $body .= '- **' . HelpCLIFormatter::formatOptionKeys($opt_array) . '**. ' . self::cliTextToMarkdown(HelpCLIFormatter::formatOptionDescription($opt_array)) . "\n";
                }
            }
            if ($body) {
                $body .= "#### Options\n\n$body\n";
            }
            return $body;
        }
        return '';
    }

    protected static function appendArguments(AnnotatedCommand $command): string
    {
        if ($args = $command->getDefinition()->getArguments()) {
            $body = "#### Arguments\n\n";
            foreach ($args as $arg) {
                $arg_array = self::argToArray($arg);
                $body .= '- **' . HelpCLIFormatter::formatArgumentName($arg_array) . '**. ' . self::cliTextToMarkdown($arg->getDescription()) . "\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendUsages(AnnotatedCommand $command): string
    {
        if ($examples = $command->getExampleUsages()) {
            $body = "#### Examples\n\n";
            foreach ($examples as $key => $value) {
                $body .= '- <code>' . $key . '</code>. ' . self::cliTextToMarkdown($value) . "\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendPreamble(AnnotatedCommand $command): string
    {
        $body = "# {$command->getName()}\n\n";
        if ($command->getDescription()) {
            $body .= self::cliTextToMarkdown($command->getDescription()) . "\n\n";
            if ($command->getHelp()) {
                $body .= self::cliTextToMarkdown($command->getHelp()) . "\n\n";
            }
        }
        return $body;
    }

    protected function writeyml(array $nav, string $dest): void
    {
        $base = file_get_contents('../misc/mkdocs_base.yml');
        $yaml_nav = Yaml::dump(['nav' => $nav], PHP_INT_MAX, 2);
        file_put_contents(Path::join($dest, 'mkdocs.yml'), $base . $yaml_nav);
    }

    /**
     * Empty target directories.
     *
     * @param $destination
     */
    protected function prepare($destination): void
    {
        $fs = new Filesystem();
        $dest = $destination;
        if ($fs->exists($dest)) {
            drush_delete_dir_contents($dest);
        }
        $fs->mkdir($dest);
        $docs_dir = Path::join($dest, 'docs');
        $fs->mkdir($docs_dir);
        $img_dir = Path::join($dest, 'docs', 'img');
        $fs->mkdir($img_dir);
        $fs->copy('../misc/favicon.ico', Path::join($img_dir, 'favicon.ico'));
        $fs->copy('../drush_logo-black.png', Path::join($img_dir, 'drush_logo-black.png'));
        $fs->copy('../misc/icon_PhpStorm.png', Path::join($img_dir, 'icon_PhpStorm.png'));
        $fs->copy('../docs/index.md', Path::join($docs_dir, 'index.md'));
        $fs->mirror('../docs/css', Path::join($docs_dir, 'css'));
    }

    /**
     * Build an array since that's what HelpCLIFormatter expects.
     *
     * @param \Symfony\Component\Console\Input\InputArgument $arg
     *
     * @return iterable
     */
    public static function argToArray(InputArgument $arg): iterable
    {
        return [
            'name' => '--' . $arg->getName(),
            'is_array' => $arg->isArray(),
            'is_required' => $arg->isRequired(),
        ];
    }

    /**
     * Build an array since that's what HelpCLIFormatter expects.
     *
     * @param \Symfony\Component\Console\Input\InputOption $opt
     *
     * @return iterable
     */
    public static function optionToArray(InputOption $opt): iterable
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

    /**
     * Convert text like <info>foo</info> to *foo*.
     *
     * @param $text
     *
     * @return string
     */
    public static function cliTextToMarkdown(string $text): string
    {
        return str_replace(['<info>', '</info>'], '*', $text);
    }
}
