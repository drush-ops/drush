<?php

declare(strict_types=1);

namespace Drush\Commands\core\cache;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Cache\Cache;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Cache an object expressed in JSON or var_export() format.',
    aliases: ['cs', 'cache-set'],
)]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
final class CacheSetCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'cache:set';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('cid', InputArgument::REQUIRED, 'id of the object to set.')
            ->addArgument('data', InputArgument::REQUIRED, 'The object to set in the cache. Use - to read the object from STDIN.')
            ->addArgument('bin', InputArgument::OPTIONAL, 'The cache bin to store the object in.', 'default')
            ->addArgument('expire', InputArgument::OPTIONAL, "'CACHE_PERMANENT', or a Unix timestamp.")
            ->addArgument('tags', InputArgument::OPTIONAL, 'A comma delimited list of cache tags.')
            ->addOption('input-format', null, InputOption::VALUE_REQUIRED, 'The format of value. Use json for complex values.', 'string')
            ->addOption('cache-get', null, InputOption::VALUE_NONE, "If the object is the result a previous fetch from the cache, only store the value in the 'data' property of the object in the cache.");
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
        $data = $input->getArgument('data');
        $bin = $input->getArgument('bin');
        $expire = $input->getArgument('expire');
        $tags = $input->getArgument('tags');

        $tags = is_string($tags) ? StringUtils::csvToArray($tags) : [];

        // In addition to prepare, this also validates.
        $data = $this->setPrepareData($data, $input);

        if (!isset($expire) || $expire == 'CACHE_PERMANENT') {
            $expire = Cache::PERMANENT;
        }

        \Drupal::cache($bin)->set($cid, $data, $expire, $tags);

        return new PropertyList(['result' => 'Cache set successfully']);
    }

    private function setPrepareData($data, InputInterface $input)
    {
        if ($data == '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // During testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $data = stream_get_contents($inputStream);
        }

        if ($input->getOption('input-format') === 'json') {
            $data = json_decode($data, true);
            if ($data === false) {
                throw new \Exception('Unable to parse JSON.');
            }
        }

        if ($input->getOption('cache-get')) {
            // $data might be an object.
            if (is_object($data) && $data->data) {
                $data = $data->data;
            } elseif (is_array($data) && isset($data['data'])) {
                // But $data returned from `drush cache:get --format=json` will be an array.
                $data = $data['data'];
            } else {
                // If $data is neither object nor array and cache-get was specified, then
                // there is a problem.
                throw new \Exception("'cache-get' was specified as an option, but the data is neither an object or an array.");
            }
        }

        return $data;
    }
}
