<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Invalidate by cache tags.',
    aliases: ['ct'],
)]
final class CacheTagsCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'cache:tags';

    public function __construct(
        private readonly CacheTagsInvalidatorInterface $invalidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tags', InputArgument::REQUIRED, 'A comma delimited list of cache tags to clear.')
            ->addUsage('cache:tag node:12,user:4');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $tags_arg = $input->getArgument('tags');
        $tags = StringUtils::csvToArray($tags_arg);

        $this->invalidator->invalidateTags($tags);
        (new DrushStyle($input, $output))->success(sprintf("Invalidated tag(s): %s.", implode(' ', $tags)));
        return Command::SUCCESS;
    }
}
