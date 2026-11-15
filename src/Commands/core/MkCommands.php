<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class MkCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use MkDocsCommand::NAME')]
    const string NAME = MkDocsCommand::NAME;

    #[Deprecated(reason: 'Use MkDocsCommand::argToArray()')]
    public static function argToArray(InputArgument $arg): iterable
    {
        return MkDocsCommand::argToArray($arg);
    }

    #[Deprecated(reason: 'Use MkDocsCommand::optionToArray()')]
    public static function optionToArray(InputOption $opt): iterable
    {
        return MkDocsCommand::optionToArray($opt);
    }

    #[Deprecated(reason: 'Use MkDocsCommand::cliTextToMarkdown()')]
    public static function cliTextToMarkdown(string $text): string
    {
        return MkDocsCommand::cliTextToMarkdown($text);
    }
}
