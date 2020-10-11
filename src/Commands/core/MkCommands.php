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
     * Build a Markdown document for each Drush command thats available on a site.
     *
     * This command is an early step when building the www.drush.org static site. Adapt it to build a similar site listing the commands that are available on your site. Also see Drush's [Github Actions workflow](https://github.com/drush-ops/drush/blob/10.x/.github/workflows/main.yml).
     *
     * @option destination The path, relative to 'docs' dir, where command docs should be written.
     *
     * @command mk:docs
     * @bootstrap max
     * @usage drush mk:docs --destination=commands/10.x
     *   Build many .md files in the docs/commands/10.x directory.
     */
    public function docs($options = ['destination' => self::REQ])
    {
        $dir_root = Drush::bootstrapManager()->getComposerRoot();
        $dir_commands = Path::join($dir_root, 'docs', $options['destination']);
        $this->prepare($dir_commands);

        $application = Drush::getApplication();
        $all = $application->all();
        $namespaced = ListCommands::categorize($all);

        // Write content files
        $pages = $pages_all = $nav = [];
        foreach ($namespaced as $category => $commands) {
            foreach ($commands as $command) {
                // Special case a single page
                if (empty($pages_all)) {
                    $pages['all'] = $options['destination'] . '/all.md';
                }

                if ($command instanceof AnnotatedCommand) {
                    $command->optionsHook();
                }
                $body = self::appendPreamble($command, $dir_root);
                $body .= self::appendUsages($command);
                $body .= self::appendArguments($command);
                $body .= self::appendOptions($command);
                if ($command instanceof AnnotatedCommand) {
                    $body .= self::appendTopics($command, $dir_commands);
                }
                $body .= self::appendAliases($command);
                $body .= self::appendPostAmble();
                $filename = str_replace(':', '_', $command->getName())  . '.md';
                $pages[$command->getName()] = $options['destination'] . "/$filename";
                file_put_contents(Path::join($dir_commands, $filename), $body);
            }
            $this->logger()->info('Found {pages} pages in {cat}', ['pages' => count($pages), 'cat' => $category]);
            $nav[] = [$category => $pages];
            $pages_all = array_merge($pages_all, $pages);
            unset($pages);
        }

        $this->writeYml($nav, $dir_root);
        $this->writeAllMd($pages_all, $dir_commands);
    }

    protected static function appendPostAmble(): string
    {
        return '!!! hint "Legend"' . "\n" . <<<EOT
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

    protected static function appendTopics(AnnotatedCommand $command, string $dir_commands): string
    {
        if ($topics = $command->getTopics()) {
            $body = "#### Topics\n\n";
            foreach ($topics as $name) {
                $value = "- `drush $name`\n";
                $topic_command = Drush::getApplication()->find($name);
                $topic_description = $topic_command->getDescription();
                if ($docs_relative = $topic_command->getAnnotationData()->get('topic')) {
                    $commandfile_path = dirname($topic_command->getAnnotationData()->get('_path'));
                    $abs = Path::makeAbsolute($docs_relative, $commandfile_path);
                    if (file_exists($abs)) {
                        $docs_path = Path::join(DRUSH_BASE_PATH, 'docs');
                        if (Path::isBasePath($docs_path, $abs)) {
                            $target_relative = Path::makeRelative($abs, $dir_commands);
                            $value = "- [$topic_description]($target_relative) ($name)";
                        } else {
                            $rel_from_root = Path::makeRelative($abs, DRUSH_BASE_PATH);
                            $value = "- [$topic_description](https://raw.githubusercontent.com/drush-ops/drush/10.x/$rel_from_root) ($name)";
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
                $body = "#### Options\n\n$body\n";
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

    protected static function appendPreamble(AnnotatedCommand $command, $root): string
    {
        $path = Path::makeRelative($command->getAnnotationData()->get('_path'), $root);
        $body = <<<EOT
---
edit_url: https://github.com/drush-ops/drush/blob/10.x/$path
---

EOT;
        $body .= "# {$command->getName()}\n\n";
        if ($command->getDescription()) {
            $body .= self::cliTextToMarkdown($command->getDescription()) . "\n\n";
            if ($command->getHelp()) {
                $body .= self::cliTextToMarkdown($command->getHelp()) . "\n\n";
            }
        }
        return $body;
    }

    protected function writeYml(array $nav, string $dest): void
    {
        $base = Yaml::parseFile(Path::join($dest, 'mkdocs_base.yml'));
        $base['nav'][] = ['Commands' => $nav];
        $yaml_nav = Yaml::dump($base, PHP_INT_MAX, 2);
        file_put_contents(Path::join($dest, 'mkdocs.yml'), $yaml_nav);
    }

    protected function writeAllMd(array $pages_all, string $dest): void
    {
        unset($pages_all['all']);
        foreach ($pages_all as $name => $page) {
            $basename = basename($page);
            $items[] = "* [$name]($basename)";
        }
        $preamble = <<<EOT
# All commands

!!! tip

    Press the ++slash++ key to Search for a command. Or use your browser's *Find in Page* feature.

EOT;
        file_put_contents(Path::join($dest, 'all.md'), $preamble . implode("\n", $items));
    }

    /**
     * Empty target directories.
     *
     * @param $destination
     */
    protected function prepare($destination): void
    {
        $fs = new Filesystem();
        if ($fs->exists($destination)) {
            drush_delete_dir_contents($destination);
        } else {
            $fs->mkdir($destination);
        }
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
            'name' => $arg->getName(),
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
