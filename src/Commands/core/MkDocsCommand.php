<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

    public function __invoke(Application $application, OutputInterface $output): int
    {
        // Drush's own directory, which holds docs/ and mkdocs_base.yml.
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
            $nav[] = [$category => $pages];
            $pages_all = array_merge($pages_all, $pages);
            $pages = [];
        }

        $filesystem->dumpFile(Path::join($destination_path, 'all.md'), self::allMd($pages_all));
        $filesystem->dumpFile(Path::join($root, 'mkdocs.yml'), self::yml($nav, $redirects, $root));
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
        $path = Path::makeRelative((string) new \ReflectionObject(self::handler($command))->getFileName(), $root);
        $body = <<<EOT
---
edit_url: https://github.com/drush-ops/drush/blob/14.x/$path
command: {$command->getName()}
title: {$command->getName()}
description: {$command->getDescription()}
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

    /**
     * The object whose class file defines the command.
     *
     * For an invokable command that is the invokable, not the Command that
     * wraps it.
     */
    private static function handler(Command $command): object
    {
        $code = $command->getCode();
        return is_object($code) && !$code instanceof \Closure ? $code : $command;
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

    /**
     * mkdocs.yml: mkdocs_base.yml plus the generated nav and redirects.
     *
     * @param array<string, string> $redirects
     */
    private static function yml(array $nav, array $redirects, string $root): string
    {
        $base = Yaml::parseFile(Path::join($root, 'mkdocs_base.yml'));
        $base['nav'][] = ['Commands' => $nav];
        $base['nav'][] = ['API' => '/api'];
        $base['plugins'][]['redirects']['redirect_maps'] = $redirects;
        $yaml = Yaml::dump($base, PHP_INT_MAX, 2);

        // Remove invalid quotes that Symfony YAML adds/needs. https://github.com/symfony/symfony/blob/6.1/src/Symfony/Component/Yaml/Inline.php#L624
        $yaml = str_replace("'!!python/name:material.extensions.emoji.twemoji'", '!!python/name:material.extensions.emoji.twemoji', $yaml);
        return str_replace("'!!python/name:material.extensions.emoji.to_svg'", '!!python/name:material.extensions.emoji.to_svg', $yaml);
    }
}
