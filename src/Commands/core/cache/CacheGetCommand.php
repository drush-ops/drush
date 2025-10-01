<?php

declare(strict_types=1);

namespace Drush\Commands\core\cache;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Fetch a cached object and display it.',
    aliases: ['cg', 'cache-get'],
)]
#[CLI\FieldLabels(labels: [
    'cid' => 'Cache ID',
    'data' => 'Data',
    'created' => 'Created',
    'expire' => 'Expire',
    'tags' => 'Tags',
    'checksum' => 'Checksum',
    'valid' => 'Valid',
])]
#[CLI\DefaultTableFields(fields: ['cid', 'data', 'created', 'expire', 'tags'])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'json')]
final class CacheGetCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'cache:get';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('cid', InputArgument::REQUIRED, 'The id of the object to fetch.')
            ->addArgument('bin', InputArgument::OPTIONAL, 'The cache bin to fetch from.', 'default')
            ->addUsage('cache:get hook_info bootstrap')
            ->addUsage('cache:get update_available_releases update');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $cid = $input->getArgument('cid');
        $bin = $input->getArgument('bin');

        $result = \Drupal::cache($bin)->get($cid);
        if (empty($result)) {
            throw new \Exception(sprintf('The %s object in the %s bin was not found.', $cid, $bin));
        }

        return new PropertyList($result);
    }
}
