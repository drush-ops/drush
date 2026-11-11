<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Fetch a cached object and display it.',
    aliases: ['cg', 'cache-get'],
    usages: [
        'cache:get hook_info bootstrap',
        'cache:get update_available_releases update',
        'cache:get my_cid --format=yaml',
    ],
)]
class CacheGetCommand
{
    use AutowireTrait;
    use CacheTrait;

    public const string NAME = 'cache:get';

    public function __construct(
        protected CacheFactoryInterface $cacheFactory,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The id of the object to fetch.')]
        string $cid,
        #[Argument('The cache bin to fetch from.')]
        string $bin = 'default',
        #[Option('Output format: json or yaml. Defaults to json.')]
        ?OutputFormat $format = null,
    ): int {
        $item = $this->cacheFactory->get($bin)->get($cid);
        if ($item === false) {
            throw new \InvalidArgumentException((string) $this->t('The @cid object in the @bin bin was not found.', [
                '@cid' => $cid,
                '@bin' => $bin,
            ]));
        }

        $fields = [
            'cid' => $item->cid,
            'data' => $item->data,
            'created' => $item->created,
            'expire' => $item->expire,
            'tags' => $item->tags,
            'valid' => $item->valid,
        ];

        $result = match ($format) {
            OutputFormat::Yaml => Yaml::encode($fields),
            default => json_encode($fields, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
