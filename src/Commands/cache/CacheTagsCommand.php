<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Invalidate by cache tags.',
    aliases: ['ct'],
    usages: ['cache:tags node:12,user:4'],
)]
class CacheTagsCommand
{
    use AutowireTrait;
    use CacheTrait;

    public const string NAME = 'cache:tags';

    public function __construct(
        protected CacheTagsInvalidatorInterface $invalidator,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('A comma delimited list of cache tags to invalidate.')]
        string $tags,
    ): int {
        $list = StringUtils::csvToArray($tags);
        $this->invalidator->invalidateTags($list);

        $io = new SymfonyStyle($input, $output);
        $io->success((string) $this->t('Invalidated tag(s): @tags.', ['@tags' => implode(' ', $list)]));

        return Command::SUCCESS;
    }
}
