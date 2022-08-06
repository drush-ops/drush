<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\ApplicationFactory;
use Drush\Commands\help\HelpCLIFormatter;
use Drush\Commands\help\ListCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

class MkCommands extends DrushCommands implements SiteAliasManagerAwareInterface, AutoloaderAwareInterface
{
    use SiteAliasManagerAwareTrait;
    use AutoloaderAwareTrait;

    /**
     * Build a Markdown document for each Drush command/generator that is available on a site.
     *
     * This command is an early step when building the www.drush.org static site. Adapt it to build a similar site listing the commands that are available on your site. Also see Drush's [Github Actions workflow](https://github.com/drush-ops/drush/blob/11.x/.github/workflows/main.yml).
     *
     * @command mk:docs
     * @bootstrap max
     * @usage drush mk:docs
     *   Build many .md files in the docs/commands and docs/generators directories.
     */
    public function docs(): void
    {
        $dir_root = Drush::bootstrapManager()->getComposerRoot();

        $destination = 'commands';
        $destination_path = Path::join($dir_root, 'docs', $destination);
        $this->prepare($destination_path);
        $application = Drush::getApplication();
        $all = $application->all();
        $namespaced = ListCommands::categorize($all);
        [$nav_commands, $pages_commands, $map_commands] = $this->writeContentFilesAndBuildNavAndBuildRedirectMap($namespaced, $destination, $dir_root, $destination_path);
        $this->writeAllMd($pages_commands, $destination_path, 'All commands');

        $destination = 'generators';
        $destination_path = Path::join($dir_root, 'docs', $destination);
        $this->prepare($destination_path);
        $factory = new ApplicationFactory($this->logger(), $this->getConfig());
        $factory->setAutoloader($this->autoloader());
        $application_generate = $factory->create();
        $all = $this->createAnnotatedCommands($application_generate, Drush::getApplication());
        $namespaced = ListCommands::categorize($all);
        [$nav_generators, $pages_generators, $map_generators] = $this->writeContentFilesAndBuildNavAndBuildRedirectMap($namespaced, $destination, $dir_root, $destination_path);
        $this->writeAllMd($pages_generators, $destination_path, 'All generators');

        $this->writeYml($nav_commands, $nav_generators, $map_commands, $map_generators, $dir_root);
    }

    public function createAnnotatedCommands(Application $application_generate, Application $application_drush): array
    {
        $application = new Application('temp');
        $definition = $application_drush->get('generate')->getDefinition();
        foreach ($application_generate->all() as $command) {
            $annotated = new AnnotatedCommand($command->getName());
            foreach (['answer', 'destination', 'dry-run'] as $key) {
                $options[$key] = $definition->getOption($key);
            }
            $annotated->addOptions($options);
            $annotated->setDescription($command->getDescription());
            $annotated->setHelp($command->getHelp());
            $annotated->setAliases($command->getAliases());
            $annotated->setTopics(['docs:generators']);
            $values = [];
            if (in_array($command->getName(), ['entity:bundle-class'])) {
                $values['version'] = '11.0';
            }
            $annotated->setAnnotationData(new AnnotationData($values));
            // Hack, until we have https://github.com/consolidation/annotated-command/pull/247
            $method = new \ReflectionMethod($annotated, 'addUsageOrExample');
            $method->setAccessible(true);
            $method->invoke($annotated, 'drush generate ' . $command->getName(), $command->getDescription());
            $commands[$command->getName()] = $annotated;
        }
        unset($commands['list'], $commands['help']);
        return $commands;
    }

    protected static function appendPostAmble(): string
    {
        return '!!! hint "Legend"' . "\n" . <<<EOT
    - An argument or option with square brackets is optional.
    - Any default value is listed at end of arg/option description.
    - An ellipsis indicates that an argument accepts multiple values separated by a space.
EOT;
    }

    protected static function appendAliases($command): string
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
                            $value = "- [$topic_description](https://raw.githubusercontent.com/drush-ops/drush/11.x/$rel_from_root) ($name)";
                        }
                    }
                }
                $body .= "$value\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendOptions($command): string
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

    protected static function appendArguments($command): string
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
        if ($usages = $command->getExampleUsages()) {
            $body = "#### Examples\n\n";
            foreach ($usages as $key => $value) {
                $body .= '- <code>' . $key . '</code>. ' . self::cliTextToMarkdown($value) . "\n";
            }
            return "$body\n";
        }
        return '';
    }

    protected static function appendPreamble($command, $root): string
    {
        $path = '';
        if ($command instanceof AnnotatedCommand) {
            $path = Path::makeRelative($command->getAnnotationData()->get('_path'), $root);
        }
        $edit_url = $path ? "https://github.com/drush-ops/drush/blob/11.x/$path" : '';
        $body = <<<EOT
---
edit_url: $edit_url
command: {$command->getName()}
---

EOT;
        $body .= "# {$command->getName()}\n\n";
        if ($command instanceof AnnotatedCommand && $version = $command->getAnnotationData()->get('version')) {
            $body .= ":octicons-tag-24: $version+\n\n";
        }
        if ($command->getDescription()) {
            $body .= self::cliTextToMarkdown($command->getDescription()) . "\n\n";
            if ($command->getHelp()) {
                $body .= self::cliTextToMarkdown($command->getHelp()) . "\n\n";
            }
        }
        return $body;
    }

    protected function writeYml(array $nav_commands, array $nav_generators, array $map_commands, array $map_generators, string $dest): void
    {
        $base = Yaml::parseFile(Path::join($dest, 'mkdocs_base.yml'));
        $base['nav'][] = ['Commands' => $nav_commands];
        $base['nav'][] = ['Generators' => $nav_generators];
        $base['plugins'][]['redirects']['redirect_maps'] = $map_commands + $map_generators;
        $yaml_nav = Yaml::dump($base, PHP_INT_MAX, 2);

        // Remove invalid quotes that Symfony YAML adds/needs. https://github.com/symfony/symfony/blob/6.1/src/Symfony/Component/Yaml/Inline.php#L624
        $yaml_nav = str_replace("'!!python/name:materialx.emoji.twemoji'", '!!python/name:materialx.emoji.twemoji', $yaml_nav);
        $yaml_nav = str_replace("'!!python/name:materialx.emoji.to_svg'", '!!python/name:materialx.emoji.to_svg', $yaml_nav);

        file_put_contents(Path::join($dest, 'mkdocs.yml'), $yaml_nav);
    }

    protected function writeAllMd(array $pages_all, string $destination_path, string $title): void
    {
        unset($pages_all['all']);
        foreach ($pages_all as $name => $page) {
            $basename = basename($page);
            $items[] = "* [$name]($basename)";
        }
        $preamble = <<<EOT
# $title

!!! tip

    Press the ++slash++ key to Search for a command. Or use your browser's *Find in Page* feature.

EOT;
        file_put_contents(Path::join($destination_path, 'all.md'), $preamble . implode("\n", $items));
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
     * @param InputArgument $arg
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
     * @param InputOption $opt
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
     */
    public static function cliTextToMarkdown(string $text): string
    {
        return str_replace(['<info>', '</info>'], '*', $text);
    }

    /**
     * Write content files, add to nav, build a redirect map.
     */
    public function writeContentFilesAndBuildNavAndBuildRedirectMap(array $namespaced, string $destination, string $dir_root, string $destination_path): array
    {
        $pages = $pages_all = $nav = $map_all = [];
        foreach ($namespaced as $category => $commands) {
            foreach ($commands as $command) {
                // Special case a single page
                if (empty($pages_all)) {
                    $pages['all'] = $destination . '/all.md';
                }

                if ($command instanceof AnnotatedCommand) {
                    $command->optionsHook();
                }
                $body = self::appendPreamble($command, $dir_root);
                if ($command instanceof AnnotatedCommand) {
                    $body .= self::appendUsages($command);
                }
                $body .= self::appendArguments($command);
                $body .= self::appendOptions($command);
                if ($command instanceof AnnotatedCommand) {
                    $body .= self::appendTopics($command, $destination_path);
                }
                $body .= self::appendAliases($command);
                if ($destination == 'commands') {
                    $body .= self::appendPostAmble();
                }
                $filename = $this->getFilename($command->getName());
                $pages[$command->getName()] = $destination . "/$filename";
                file_put_contents(Path::join($destination_path, $filename), $body);

                if ($map = $this->getRedirectMap($command, $destination)) {
                    $map_all = array_merge($map_all, $map);
                }
                unset($map);
            }
            $this->logger()->info('Found {pages} pages in {cat}', ['pages' => count($pages), 'cat' => $category]);
            $nav[] = [$category => $pages];
            $pages_all = array_merge($pages_all, $pages);
            unset($pages);
        }
        return [$nav, $pages_all, $map_all];
    }

    protected function getRedirectMap(Command $command, string $destination): array
    {
        $map = [];
        foreach ($command->getAliases() as $alias) {
            // Skip trivial aliases that differ by a dash.
            if (str_replace([':', '-'], '', $command->getName()) == str_replace([':', '-'], '', $alias)) {
                continue;
            }
            $map[Path::join($destination, $this->getFilename($alias))] = Path::join($destination, $this->getFilename($command->getName()));
        }
        return $map;
    }

    /**
     * Get a filename from a command.
     */
    public function getFilename(string $name): string
    {
        return str_replace(':', '_', $name) . '.md';
    }
}
