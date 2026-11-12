<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Evaluates a snippet of PHP and prints what it returns.
 *
 * The Bootstrap attribute is the one Drush-specific piece left: it keeps
 * Drush's command discovery from bootstrapping Drupal before the command
 * runs, so the command also works outside a Drupal site. Under Drush the
 * command then bootstraps as far as it can itself. Elsewhere (dr, a kernel
 * test) there is no BootstrapManager and Drupal is already available.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Evaluate arbitrary php code after bootstrapping Drupal (if available).',
    aliases: ['eval', 'ev', 'php-eval'],
    usages: [
        "php:eval '\$node = \Drupal\\node\Entity\Node::load(1); print \$node->getTitle();'",
        'php:eval "\Drupal::service(\'file_system\')->copy(\'$HOME/Pictures/image.jpg\', \'public://image.jpg\');"',
        'php:eval "node_access_rebuild();"',
    ],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class PhpEvalCommand
{
    use AutowireTrait;

    public const string NAME = 'php:eval';

    public function __construct(
        protected ?BootstrapManager $bootstrapManager = null,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('PHP code. If shell escaping gets too tedious, consider using the php:script command.')]
        string $code,
        #[Option('An alternative output format such as json or yaml. By default the returned value prints via var_export().')]
        ?OutputFormat $format = null,
    ): int {
        $this->bootstrapManager?->bootstrapMax(DrupalBootLevels::FULL);

        $value = eval($code . ';');

        $result = match ($format) {
            OutputFormat::Json => json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($value),
            default => $value === null ? '' : var_export($value, true),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $result = rtrim($result, "\n");
        if ($result !== '') {
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
