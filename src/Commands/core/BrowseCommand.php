<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a link to a given path',
    usages: [
        'browse node/1',
    ],
)]
final class BrowseCommand
{
    use AutowireTrait;
    use StringTranslationTrait;

    public const string NAME = 'browse';

    public function __invoke(
        OutputInterface $output,
        #[Argument('Path to link to. If omitted, the site front page is used.')]
        string $path = '',
    ): int {
        // Url::fromUserInput() is core's helper for user-supplied paths: it
        // resolves routed paths and falls back to an unrouted URL for the rest,
        // which the path.validator service alone would reject.
        // One leading slash is tolerated. Two would name a host, which core
        // silently drops, so reject that outright.
        $path = str_starts_with($path, '/') ? substr($path, 1) : $path;
        if (str_starts_with($path, '/')) {
            throw new \InvalidArgumentException((string) $this->t('@path is not a valid site path: it must not start with //.', ['@path' => '/' . $path]));
        }
        try {
            $link = Url::fromUserInput('/' . $path, ['absolute' => true])->toString();
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException((string) $this->t('@path is not a valid site path: @reason', ['@path' => $path, '@reason' => $e->getMessage()]), 0, $e);
        }
        $output->writeln($link);

        return Command::SUCCESS;
    }
}
