<?php

declare(strict_types=1);

namespace Drush\Dr\standard\Command\state;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\State\StateInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a state value.',
    aliases: ['sget', 'state-get'],
    usages: [
        'state:get system.cron_last',
        'state:get drupal_css_cache_files --format=yaml',
    ],
)]
final class StateGetCommand
{
    use AutowireTrait;

    public const string NAME = 'state:get';

    public function __construct(
        protected StateInterface $state,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The state key, for example system.cron_last.')]
        string $key,
        #[Option('An alternative output format such as json or yaml. By default scalars print as-is and anything else as yaml.')]
        ?OutputFormat $format = null,
    ): int {
        $value = $this->state->get($key);

        $result = match ($format) {
            OutputFormat::Json => json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($value),
            default => $this->renderRaw($value),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $result = rtrim($result, "\n");
        if ($result !== '') {
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }

    /**
     * Renders a value the way the old 'string' formatter did.
     *
     * Scalars print as-is, so scripts can consume the value directly. A
     * missing key prints nothing. Arrays and objects fall back to yaml so
     * that nothing is lost.
     */
    protected function renderRaw(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_scalar($value) => (string) $value,
            default => Yaml::encode($value),
        };
    }
}
