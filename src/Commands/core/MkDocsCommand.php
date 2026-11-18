<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Build a Markdown document for each available command.',
)]
final class MkDocsCommand
{
    public const string NAME = 'mk:docs';

    private const string DESTINATION = 'commands';

    /**
     * The Drush branch that edit links point to. Update it for each major.
     */
    private const string BRANCH = '15.x';

    public function __invoke(Application $application, OutputInterface $output): int
    {
        // Drush's own directory, which holds docs/ and mkdocs.yml.
        $root = dirname(__DIR__, 3);
        $destination_path = Path::join($root, 'docs', self::DESTINATION);
        $output->writeln("Writing to $destination_path", OutputInterface::VERBOSITY_VERBOSE);

        // Start from an empty directory.
        $filesystem = new Filesystem();
        $filesystem->remove($destination_path);
        $filesystem->mkdir($destination_path);

        if ($application->has('completion')) {
            $application->get('completion')->setHidden(true);
        }

        $global_options = self::globalOptions($application);
        $pages = $pages_all = $nav = $redirects = [];
        foreach (self::categorize($application->all()) as $category => $commands) {
            foreach ($commands as $command) {
                // The first category also links to the page that lists everything.
                if ($pages_all === []) {
                    $pages['all'] = self::DESTINATION . '/all.md';
                }

                $body = self::preamble($command, $root)
                    . self::usages($command)
                    . self::arguments($command)
                    . self::options($command)
                    . $global_options
                    . self::aliases($command)
                    . self::legend();
                $filename = self::getFilename($command->getName());
                $pages[$command->getName()] = self::DESTINATION . "/$filename";
                $filesystem->dumpFile(Path::join($destination_path, $filename), $body);

                $redirects = array_merge($redirects, self::getRedirectMap($command, self::DESTINATION));
            }
            $output->writeln(sprintf('Found %d pages in %s', count($pages), $category), OutputInterface::VERBOSITY_VERBOSE);
            // Each entry is a single-entry map, with the page relative to this directory.
            $nav[] = [$category => array_map(static fn(string $name, string $page): array => [$name => basename($page)], array_keys($pages), $pages)];
            $pages_all = array_merge($pages_all, $pages);
            $pages = [];
        }

        $filesystem->dumpFile(Path::join($destination_path, 'all.md'), self::allMd($pages_all));
        // The navigation of this directory, which docs/.nav.yml includes.
        $filesystem->dumpFile(Path::join($destination_path, '.nav.yml'), Yaml::dump(['title' => 'Commands', 'nav' => $nav], PHP_INT_MAX, 2));
        // mkdocs.yml inherits this file, which adds the alias redirects to its own.
        // It is committed with an empty map so that the site builds before this runs.
        $comment = "# mkdocs.yml inherits this file. The mk:docs command fills in the alias redirects.\n# Do not commit the result; keep the map empty in Git.\n";
        $filesystem->dumpFile(Path::join($root, 'mkdocs_redirects.yml'), $comment . Yaml::dump(['plugins' => ['redirects' => ['redirect_maps' => $redirects]]], PHP_INT_MAX, 2));
        return Command::SUCCESS;
    }

    /**
     * Convert text like <info>foo</info> to *foo*. Also convert to Markdown links.
     */
    public static function cliTextToMarkdown(string $text): string
    {
        $text = str_replace(['<info>', '</info>'], '*', $text);
        return (string) preg_replace('/<href=([^>]+)>([^<]+)<\/>/', '[$2]($1)', $text);
    }

    /**
     * Format an argument name: [name] when optional, name... when an array.
     */
    public static function formatArgument(InputArgument $argument): string
    {
        $element = $argument->getName();
        if (!$argument->isRequired()) {
            $element = "[$element]";
        } elseif ($argument->isArray()) {
            $element = "$element ($element)";
        }
        return $argument->isArray() ? "$element..." : $element;
    }

    /**
     * Format option keys, such as "-f, --format=FORMAT".
     */
    public static function formatOptionKeys(InputOption $option): string
    {
        $value = '';
        if ($option->acceptValue()) {
            $value = '=' . strtoupper($option->getName());
            if (!$option->isValueRequired()) {
                $value = "[$value]";
            }
        }
        $shortcut = $option->getShortcut() ? sprintf('-%s, ', $option->getShortcut()) : '';
        return sprintf('%s--%s%s', $shortcut, $option->getName(), $value);
    }

    /**
     * An option description, followed by its default value if it has one.
     */
    public static function formatOptionDescription(InputOption $option): string
    {
        if (!$option->getDefault()) {
            return $option->getDescription();
        }
        $default = implode(' ', (array) $option->getDefault());
        // Avoid info tags for large strings https://github.com/drush-ops/drush/issues/4639.
        if (strlen($default) <= 100) {
            $default = "<info>$default</info>";
        }
        return $option->getDescription() . " [default: $default]";
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
     * Group visible commands by namespace, sorted.
     *
     * @param array<string, Command> $all
     *   Commands keyed by name and by alias.
     *
     * @return array<string, array<string, Command>>
     */
    public static function categorize(array $all): array
    {
        $namespaced = [];
        foreach ($all as $key => $command) {
            if (!in_array($key, $command->getAliases()) && !$command->isHidden()) {
                $namespaced[explode(':', $key)[0]][$key] = $command;
            }
        }

        // Avoid solo namespaces.
        $global = [];
        foreach ($namespaced as $namespace => $commands) {
            if (count($commands) === 1) {
                $global += $commands;
                unset($namespaced[$namespace]);
            }
        }
        $namespaced['_global'] = $global;

        ksort($namespaced);
        foreach ($namespaced as &$commands) {
            ksort($commands);
        }
        return $namespaced;
    }

    private static function preamble(Command $command, string $root): string
    {
        $file = (string) new \ReflectionObject(self::handler($command))->getFileName();
        $core_dir = dirname((string) new \ReflectionClass(\Drupal::class)->getFileName(), 2);
        $symfony_version = InstalledVersions::getPrettyVersion('symfony/console') ?? '';
        $edit_url = self::editUrl($file, $root, $core_dir, \Drupal::VERSION, $symfony_version);
        // Dump as YAML so that a colon in a description stays valid front matter.
        $front_matter = array_filter(['edit_url' => $edit_url]) + [
            'command' => $command->getName(),
            'title' => $command->getName(),
            'description' => $command->getDescription(),
        ];
        $body = "---\n" . Yaml::dump($front_matter) . "---\n\n";
        $body .= "# {$command->getName()}\n\n";

        if (!self::isDrushCommand($command)) {
            $body .= <<<EOT
!!! tip

    This command is part of Drupal core, not Drush. Use the [Drupal issue queue](https://www.drupal.org/project/issues/drupal) for bug reports and feature requests.


EOT;
        }

        if ($command->getDescription()) {
            $body .= self::cliTextToMarkdown($command->getDescription()) . "\n\n";
            if ($command->getHelp()) {
                $body .= self::cliTextToMarkdown($command->getHelp()) . "\n\n";
            }
        }
        return $body;
    }

    /**
     * The URL for editing the file that defines a command, if known.
     *
     * @param string $file
     *   The absolute path of the file.
     * @param string $root
     *   Drush's root directory.
     * @param string $core_dir
     *   Drupal's core directory.
     * @param string $core_version
     *   Drupal core's version, such as 11.2.5 or 12.0-dev.
     * @param string $symfony_version
     *   The installed symfony/console version, such as v8.0.1 or 8.1.x-dev.
     */
    public static function editUrl(string $file, string $root, string $core_dir, string $core_version, string $symfony_version): ?string
    {
        if (Path::isBasePath(Path::join($root, 'src'), $file)) {
            return sprintf('https://github.com/drush-ops/drush/blob/%s/%s', self::BRANCH, Path::makeRelative($file, $root));
        }
        if (Path::isBasePath($core_dir, $file)) {
            // A dev version is built from core's default branch; a release has a tag.
            $ref = str_ends_with($core_version, '-dev') ? 'main' : $core_version;
            return sprintf('https://git.drupalcode.org/project/drupal/-/blob/%s/core/%s', $ref, Path::makeRelative($file, $core_dir));
        }
        if (preg_match('#/vendor/symfony/console/(.+)$#', $file, $matches)) {
            // A dev version such as 8.1.x-dev is built from the 8.1 branch.
            $ref = preg_replace('/\.x-dev$/', '', $symfony_version);
            return "https://github.com/symfony/console/blob/$ref/$matches[1]";
        }
        return null;
    }

    /**
     * The object whose class file defines the command.
     *
     * For an invokable command that is the invokable, not the Command that
     * wraps it. Commands from the container arrive as a LazyCommand, and
     * AddConsoleCommandPass wraps an invokable in a Closure bound to it.
     */
    private static function handler(Command $command): object
    {
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }
        $code = $command->getCode();
        if ($code instanceof \Closure) {
            $code = new \ReflectionFunction($code)->getClosureThis();
        }
        return is_object($code) ? $code : $command;
    }

    /**
     * Whether Drush, rather than Drupal core, provides the command.
     */
    private static function isDrushCommand(Command $command): bool
    {
        return str_starts_with(self::handler($command)::class, 'Drush\\');
    }

    private static function usages(Command $command): string
    {
        $body = "#### Examples\n\n";
        foreach ($command->getUsages() as $value) {
            $body .= "- <code>$value</code>\n";
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
            $body .= '- **' . self::formatArgument($arg) . '**. ' . self::cliTextToMarkdown($arg->getDescription()) . "\n";
        }
        return "$body\n";
    }

    private static function options(Command $command): string
    {
        $body = self::optionList($command->getDefinition()->getOptions());
        return $body ? "#### Options\n\n$body\n" : '';
    }

    private static function globalOptions(Application $application): string
    {
        $body = self::optionList($application->getDefinition()->getOptions());
        return $body ? "#### Global Options\n\n$body\n" : '';
    }

    /**
     * @param InputOption[] $options
     */
    private static function optionList(array $options): string
    {
        $body = '';
        foreach ($options as $option) {
            $body .= '- **' . self::formatOptionKeys($option) . '**. ' . self::cliTextToMarkdown(self::formatOptionDescription($option)) . "\n";
            if ($option->isNegatable()) {
                $body .= sprintf("- **--no-%s**. Negate the --%s option.\n", $option->getName(), $option->getName());
            }
        }
        return $body;
    }

    private static function aliases(Command $command): string
    {
        $aliases = $command->getAliases();
        if ($aliases === []) {
            return '';
        }
        $body = "#### Aliases\n\n";
        foreach ($aliases as $value) {
            $body .= "- $value\n";
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
     * The page that lists every command.
     *
     * @param array<string, string> $pages
     *   Page paths keyed by command name.
     */
    private static function allMd(array $pages): string
    {
        unset($pages['all']);
        $items = [];
        foreach ($pages as $name => $page) {
            $items[] = "* [$name](" . basename($page) . ')';
        }
        $preamble = <<<EOT
# All commands

!!! tip

    Press the ++slash++ key to Search for a command. Or use your browser's *Find in Page* feature.

EOT;
        return $preamble . implode("\n", $items);
    }
}
