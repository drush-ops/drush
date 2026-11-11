<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Cache an object expressed in JSON or string format.',
    aliases: ['cs', 'cache-set'],
    usages: [
        'cache:set my_cid "some value"',
        'cache:set my_cid \'{"key": "value"}\' default CACHE_PERMANENT node:1,user:2 --input-format=json',
        'cache:set my_cid - --input-format=json < value.json',
    ],
)]
class CacheSetCommand
{
    use AutowireTrait;
    use CacheTrait;

    public const string NAME = 'cache:set';

    /**
     * Accepted values of --input-format.
     */
    public const array INPUT_FORMATS = ['string', 'json'];

    public function __construct(
        protected CacheFactoryInterface $cacheFactory,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The id of the object to set.')]
        string $cid,
        #[Argument('The object to set in the cache. Use - to read the object from STDIN.')]
        string $data,
        #[Argument('The cache bin to store the object in.')]
        string $bin = 'default',
        #[Argument("'CACHE_PERMANENT', or a Unix timestamp.")]
        ?string $expire = null,
        #[Argument('A comma delimited list of cache tags.')]
        ?string $tags = null,
        #[Option('The format of value: string or json. Use json for complex values.', suggestedValues: self::INPUT_FORMATS)]
        string $inputFormat = 'string',
    ): int {
        if (!in_array($inputFormat, self::INPUT_FORMATS, true)) {
            throw new \InvalidArgumentException((string) $this->t('Unknown input format: @format. Allowed values: @allowed', [
                '@format' => $inputFormat,
                '@allowed' => implode(', ', self::INPUT_FORMATS),
            ]));
        }

        if ($data === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // Under CommandTester this reads what setInputs() provided.
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $data = stream_get_contents($stream ?? STDIN);
        }

        $value = $data;
        if ($inputFormat === 'json') {
            try {
                $value = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException((string) $this->t('Unable to parse JSON: @message', ['@message' => $e->getMessage()]), 0, $e);
            }
        }

        $expire = ($expire === null || $expire === 'CACHE_PERMANENT') ? Cache::PERMANENT : (int) $expire;
        $tags = is_string($tags) ? StringUtils::csvToArray($tags) : [];

        $this->cacheFactory->get($bin)->set($cid, $value, $expire, $tags);

        $io = new SymfonyStyle($input, $output);
        $io->success((string) $this->t('Cached @cid in the @bin bin.', ['@cid' => $cid, '@bin' => $bin]));

        return Command::SUCCESS;
    }
}
