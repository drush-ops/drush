<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\OutputFormat;
use Drush\Drush;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Bootstrap attribute is the one Drush-specific piece left: the command
 * must work without a Drupal site, so messages are plain strings, not t().
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Show Drush version.',
    usages: [
        'version --field=drush-version',
        'version --format=json',
    ],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class VersionCommand
{
    public const string NAME = 'version';

    public const string FIELD = 'drush-version';

    public function __invoke(
        OutputInterface $output,
        #[Option('An alternative output format such as json or yaml.')]
        ?OutputFormat $format = null,
        #[Option('Print the bare version. The only field is drush-version.', suggestedValues: [self::FIELD])]
        ?string $field = null,
    ): int {
        if ($field !== null && $field !== self::FIELD) {
            throw new \InvalidArgumentException(sprintf('Unknown field %s. The only field is %s.', $field, self::FIELD));
        }

        $version = (string) Drush::getVersion();
        $result = match (true) {
            $field !== null => $version,
            $format === OutputFormat::Json => json_encode([self::FIELD => $version], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            $format === OutputFormat::Yaml => Yaml::encode([self::FIELD => $version]),
            default => 'Drush version : ' . Drush::sanitizeVersionString($version),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
