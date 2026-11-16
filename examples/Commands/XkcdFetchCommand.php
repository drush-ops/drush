<?php

declare(strict_types=1);

namespace Drupal\example\Command;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An example console command.
 *
 * Drupal discovers classes with #[AsCommand] in a module's src/Command
 * directory and autowires their constructor arguments. To try this one, copy it
 * to modules/example/src/Command/XkcdFetchCommand.php in a module named
 * 'example', enable the module, then run `dr xkcd random`.
 */
#[AsCommand(
    name: self::NAME,
    description: 'Display a link to an xkcd cartoon.',
    aliases: ['xkcd'],
    usages: [
        '123',
        'random',
    ],
)]
final class XkcdFetchCommand
{
    use StringTranslationTrait;

    public const string NAME = 'xkcd:fetch';

    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('A cartoon number, or "random". If omitted, the latest cartoon is used.')]
        string $search = '',
    ): int {
        $url = match (true) {
            $search === '' => 'https://xkcd.com',
            ctype_digit($search) => 'https://xkcd.com/' . $search,
            $search === 'random' => 'https://xkcd.com/' . random_int(1, $this->latestNumber()),
            default => throw new \InvalidArgumentException((string) $this->t('@search is not a cartoon number or "random".', ['@search' => $search])),
        };
        $output->writeln($url);
        return Command::SUCCESS;
    }

    /**
     * Returns the number of the most recent cartoon.
     */
    private function latestNumber(): int
    {
        $response = $this->httpClient->request('GET', 'https://xkcd.com/info.0.json');
        $info = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return (int) $info['num'];
    }
}
