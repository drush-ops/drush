<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\generate\ApplicationFactory;
use Drush\Commands\help\HelpCLIFormatter;
use Drush\Commands\help\ListCommand;
use Drush\Config\DrushConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Build a Markdown document for each available Drush command/generator.',
)]
final class MkDocsCommand
{
    use AutowireTrait;

    public const string NAME = 'mk:docs';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
        private readonly LoggerInterface $logger,
        private readonly ContainerInterface $drushContainer,
        private readonly DrushConfig $drushConfig,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function __invoke(Application $application): int
    {
        $root = $this->bootstrapManager->getComposerRoot();

        $application->get('completion')->setHidden(true);
        $commands = ListCommand::categorize($application->all());
        [$nav_commands, $map_commands] = $this->writeSection($commands, 'commands', 'All commands', $root, $application);

        $application_generate = (new ApplicationFactory($this->drushContainer, $this->logger))->create();
        $generators = ListCommand::categorize($this->createAnnotatedCommands($application_generate, $application));
        [$nav_generators, $map_generators] = $this->writeSection($generators, 'generators', 'All generators', $root, $application);

        $this->writeYml($nav_commands, $nav_generators, $map_commands + $map_generators, $root);
        return Command::SUCCESS;
    }

    /**
     * Build an array since that's what HelpCLIFormatter expects.
     *
     * @return array{name: string, is_array: bool, is_required: bool}
     */
    public static function argToArray(InputArgument $arg): array
    {
        return [
            'name' => $arg->getName(),
            'is_array' => $arg->isArray(),
            'is_required' => $arg->isRequired(),
        ];
    }

    /**
     * Build an array since that's what HelpCLIFormatter expects.
     */
    public static function optionToArray(InputOption $opt): array
    {
        $return = [
            'name' => '--' . $opt->getName(),
            'accept_value' => $opt->acceptValue(),
            'is_value_required' => $opt->isValueRequired(),
            'shortcut' => $opt->getShortcut(),
            'description' => $opt->getDescription(),
        ];
        if ($opt->getDefault()) {
            $return['defaults'] = (array) $opt->getDefault();
        }
        return $return;
    }

    /**
     * Convert text like <info>foo</info> to *foo*. Also convert to Markdown links.
     */
    public static function cliTextToMarkdown(string $text): string
    {
        $text = str_replace(['<info>', '</info>'], '*', $text);
        $text = (string) preg_replace('/<href=([^>]+)>([^<]+)<\/>/', '[$2]($1.md)', $text);
        // Strip the baseUrl and let mkdocs make relative links.
        return str_replace(HelpLinks::getDocsUrlBase(), '..', $text);
    }

    /**
     * Get a page filename from a command name.
     */
    public static function getFilename(string $name): string
    {
        return str_replace(':', '_', $name) . '.md';
    }

    /**
     * Map each alias page to the page of its command.
     *
     * @return array<string, string>
     */
    public static function getRedirectMap(Command $command, string $destination): array
    {
        $map = [];
        foreach ($command->getAliases() as $alias) {
            // Skip trivial aliases that differ by a dash.
            if (str_replace([':', '-'], '', $command->getName()) === str_replace([':', '-'], '', $alias)) {
                continue;
            }
            $map[Path::join($destination, self::getFilename($alias))] = Path::join($destination, self::getFilename($command->getName()));
        }
        return $map;
    }

    /**
     * Convert generators into Annotated commands (for Help).
     *
     * @return array<string, AnnotatedCommand>
     */
    private function createAnnotatedCommands(Application $application_generate, Application $application): array
    {
        $definition = $application->get('generate')->getDefinition();
        $options = [];
        foreach (['answer', 'destination', 'dry-run'] as $key) {
            $options[$key] = $definition->getOption($key);
        }

        $commands = [];
        foreach ($application_generate->all() as $command) {
            $annotated = new AnnotatedCommand($command->getName());
            $annotated->addOptions($options);
            $annotated->setDescription($command->getDescription());
            $annotated->setHelp($command->getHelp());
            $annotated->setAliases($command->getAliases());
            $annotated->setTopics([DocsCommands::GENERATORS]);
            $annotated->setHidden($command->isHidden());
            $values = $command->getName() === 'entity:bundle-class' ? ['version' => '11.0'] : [];
            $annotated->setAnnotationData(new AnnotationData($values));
            $annotated->addUsageOrExample('drush generate ' . $command->getName(), $command->getDescription());
            $commands[$command->getName()] = $annotated;
        }
        return $commands;
    }

    /**
     * Write the pages of docs/commands or docs/generators.
     *
     * @param array<string, array<string, Command>> $namespaced
     *   Commands keyed by category, then name.
     *
     * @return array{array<int, array<string, array<string, string>>>, array<string, string>}
     *   The nav entries and the redirect map.
     */
    private function writeSection(array $namespaced, string $destination, string $title, string $root, Application $application): array
    {
        $destination_path = Path::join($root, 'docs', $destination);
        $this->logger->debug('Writing to {path}', ['path' => $destination_path]);
        // Start from an empty directory.
        $this->filesystem->remove($destination_path);
        $this->filesystem->mkdir($destination_path);

        $pages = $pages_all = $nav = $redirects = [];
        foreach ($namespaced as $category => $commands) {
            foreach ($commands as $command) {
                // The first category also links to the page that lists everything.
                if ($pages_all === []) {
                    $pages['all'] = "$destination/all.md";
                }

                if ($command instanceof AnnotatedCommand) {
                    $command->optionsHook();
                }
                $body = self::preamble($command, $root);
                $body .= self::usages($command);
                $body .= self::arguments($command);
                $body .= self::options($command);
                if ($destination === 'commands') {
                    $body .= self::globalOptions($application);
                }
                if ($command instanceof AnnotatedCommand) {
                    $body .= $this->topics($command, $destination_path, $application);
                }
                $body .= self::aliases($command);
                if ($destination === 'commands') {
                    $body .= self::legend();
                }
                $filename = self::getFilename($command->getName());
                $pages[$command->getName()] = "$destination/$filename";
                $this->filesystem->dumpFile(Path::join($destination_path, $filename), $body);

                $redirects = array_merge($redirects, self::getRedirectMap($command, $destination));
            }
            $this->logger->info('Found {pages} pages in {cat}', ['pages' => count($pages), 'cat' => $category]);
            $nav[] = [$category => $pages];
            $pages_all = array_merge($pages_all, $pages);
            $pages = [];
        }

        $this->writeAllMd($pages_all, $destination_path, $title);
        return [$nav, $redirects];
    }

    private static function preamble(Command $command, string $root): string
    {
        if ($command instanceof AnnotatedCommand) {
            $path = Path::makeRelative($command->getAnnotationData()->get('_path'), $root);
        } else {
            $path = Path::makeRelative((new \ReflectionObject(self::handler($command)))->getFileName(), $root);
        }
        $edit_url = $path ? "https://github.com/drush-ops/drush/blob/14.x/$path" : '';
        $body = <<<EOT
---
edit_url: $edit_url
command: {$command->getName()}
title: {$command->getName()}
description: {$command->getDescription()}
---

EOT;
        $body .= "# {$command->getName()}\n\n";
        $body .= self::version($command);

        if ($command->getDescription()) {
            $body .= self::cliTextToMarkdown($command->getDescription()) . "\n\n";
            if ($command->getHelp()) {
                $body .= self::cliTextToMarkdown($command->getHelp()) . "\n\n";
            }
        }
        return $body;
    }

    /**
     * The object that carries a command's attributes.
     *
     * For an invokable command that is the invokable, not the Command that
     * wraps it.
     */
    private static function handler(Command $command): object
    {
        $code = $command->getCode();
        return is_object($code) && !$code instanceof \Closure ? $code : $command;
    }

    private static function version(Command $command): string
    {
        if ($command instanceof AnnotatedCommand && $version = $command->getAnnotationData()->get('version')) {
            return ":octicons-tag-24: $version+\n\n";
        }
        if (str_starts_with($command->getName(), 'yaml:')) {
            return ":octicons-tag-24: 12.0+\n\n";
        }
        $attributes = (new \ReflectionObject(self::handler($command)))->getAttributes(CLI\Version::class);
        if ($attributes !== []) {
            return ":octicons-tag-24: {$attributes[0]->newInstance()->version}+\n\n";
        }
        return '';
    }

    private static function usages(Command $command): string
    {
        $body = "#### Examples\n\n";
        if ($command instanceof AnnotatedCommand) {
            foreach ($command->getExampleUsages() as $key => $value) {
                $body .= '- <code>' . $key . '</code>. ' . self::cliTextToMarkdown($value) . "\n";
            }
        } else {
            foreach ($command->getUsages() as $value) {
                $body .= '- <code>' . $value . '</code>' . "\n";
            }
        }
        return "$body\n";
    }

    private static function arguments(Command $command): string
    {
        $args = $command->getDefinition()->getArguments();
        if ($args === []) {
            return '';
        }
        $body = "#### Arguments\n\n";
        foreach ($args as $arg) {
            $body .= '- **' . HelpCLIFormatter::formatArgumentName(self::argToArray($arg)) . '**. ' . self::cliTextToMarkdown($arg->getDescription()) . "\n";
        }
        return "$body\n";
    }

    private static function options(Command $command): string
    {
        $body = '';
        foreach ($command->getDefinition()->getOptions() as $opt) {
            if (!HelpCLIFormatter::isGlobalOption($opt->getName())) {
                $opt_array = self::optionToArray($opt);
                $body .= '- **' . HelpCLIFormatter::formatOptionKeys($opt_array) . '**. ' . self::cliTextToMarkdown(HelpCLIFormatter::formatOptionDescription($opt_array)) . "\n";
            }
            if ($command->getDefinition()->hasNegation('no-' . $opt->getName())) {
                $body .= sprintf('- **--no-' . $opt->getName() . '**. Negate the --%s option.', $opt->getName()) . "\n";
            }
        }
        return $body ? "#### Options\n\n$body\n" : '';
    }

    private static function globalOptions(Application $application): string
    {
        $opts = $application->getDefinition()->getOptions();
        if ($opts === []) {
            return '';
        }
        $body = '';
        foreach ($opts as $key => $value) {
            if (!in_array($key, HelpCLIFormatter::OPTIONS_GLOBAL_IMPORTANT)) {
                continue;
            }
            // The values don't go through standard formatting since we want to show http://default not the uri that was used when running this command.
            $body .= '- **' . HelpCLIFormatter::formatOptionKeys(self::optionToArray($value)) . '**. ' . self::cliTextToMarkdown($value->getDescription()) . "\n";
        }
        $body .= '- To see all global options, run <code>drush core:global-options</code>.' . "\n";
        return "#### Global Options\n\n$body\n";
    }

    private function topics(AnnotatedCommand $command, string $dir_commands, Application $application): string
    {
        $topics = $command->getTopics();
        if ($topics === []) {
            return '';
        }
        $base = $this->drushConfig->get('drush.base-dir');
        $body = "#### Topics\n\n";
        foreach ($topics as $name) {
            $value = "- `drush $name`\n";
            /** @var AnnotatedCommand $topic_command */
            $topic_command = $application->find($name);
            $topic_description = $topic_command->getDescription();
            if ($docs_relative = $topic_command->getAnnotationData()->get('topic')) {
                $commandfile_path = dirname($topic_command->getAnnotationData()->get('_path'));
                $abs = Path::makeAbsolute($docs_relative, $commandfile_path);
                if (file_exists($abs)) {
                    if (Path::isBasePath(Path::join($base, 'docs'), $abs)) {
                        $target_relative = Path::makeRelative($abs, $dir_commands);
                        $value = "- [$topic_description]($target_relative) ($name)";
                    } else {
                        $rel_from_root = Path::makeRelative($abs, $base);
                        $value = "- [$topic_description](https://raw.githubusercontent.com/drush-ops/drush/14.x/$rel_from_root) ($name)";
                    }
                }
            }
            $body .= "$value\n";
        }
        return "$body\n";
    }

    private static function aliases(Command $command): string
    {
        $aliases = $command->getAliases();
        if ($aliases === []) {
            return '';
        }
        $body = "#### Aliases\n\n";
        foreach ($aliases as $value) {
            $body .= '- ' . $value . "\n";
        }
        return "$body\n";
    }

    private static function legend(): string
    {
        return '!!! hint "Legend"' . "\n" . <<<EOT
    - An argument or option with square brackets is optional.
    - Any default value is listed at end of arg/option description.
    - An ellipsis indicates that an argument accepts multiple values separated by a space.
EOT;
    }

    /**
     * @param array<string, string> $pages
     *   Page paths keyed by command name.
     */
    private function writeAllMd(array $pages, string $destination_path, string $title): void
    {
        unset($pages['all']);
        $items = [];
        foreach ($pages as $name => $page) {
            $items[] = "* [$name](" . basename($page) . ')';
        }
        $preamble = <<<EOT
# $title

!!! tip

    Press the ++slash++ key to Search for a command. Or use your browser's *Find in Page* feature.

EOT;
        $this->filesystem->dumpFile(Path::join($destination_path, 'all.md'), $preamble . implode("\n", $items));
    }

    /**
     * Write mkdocs.yml: mkdocs_base.yml plus the generated nav and redirects.
     *
     * @param array<string, string> $redirects
     */
    private function writeYml(array $nav_commands, array $nav_generators, array $redirects, string $root): void
    {
        $base = Yaml::parseFile(Path::join($root, 'mkdocs_base.yml'));
        $base['nav'][] = ['Commands' => $nav_commands];
        $base['nav'][] = ['Generators' => $nav_generators];
        $base['nav'][] = ['API' => '/api'];
        $base['plugins'][]['redirects']['redirect_maps'] = $redirects;
        $yaml = Yaml::dump($base, PHP_INT_MAX, 2);

        // Remove invalid quotes that Symfony YAML adds/needs. https://github.com/symfony/symfony/blob/6.1/src/Symfony/Component/Yaml/Inline.php#L624
        $yaml = str_replace("'!!python/name:material.extensions.emoji.twemoji'", '!!python/name:material.extensions.emoji.twemoji', $yaml);
        $yaml = str_replace("'!!python/name:material.extensions.emoji.to_svg'", '!!python/name:material.extensions.emoji.to_svg', $yaml);

        $this->filesystem->dumpFile(Path::join($root, 'mkdocs.yml'), $yaml);
    }
}
