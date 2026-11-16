<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Command\OutputFormat;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'List all config entities depending on a given config entity.',
    aliases: ['wc'],
    usages: [
        'why:config node.type.article',
        'why:config node.type.article --format=yaml',
    ],
)]
final class WhyConfigCommand
{
    use DependencyTreeTrait;

    public const string NAME = 'why:config';

    public function __construct(
        protected ConfigManagerInterface $configManager,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The config entity to check dependents for.')]
        string $config,
        #[Option('An alternative output format such as json or yaml. Defaults to a tree.')]
        ?OutputFormat $format = null,
    ): int {
        if (!$this->configManager->loadConfigEntityByName($config)) {
            throw new \InvalidArgumentException((string) $this->t('Invalid @config config entity.', ['@config' => $config]));
        }

        $this->resetTree();
        $this->buildDependents($this->scanConfigs()['config']);

        if (!$this->hasDependents($config)) {
            // On stderr, so that json and yaml output stays parseable.
            (new SymfonyStyle($input, $output))->getErrorStyle()->note((string) $this->t('No other config entity depends on @config.', ['@config' => $config]));
            return Command::SUCCESS;
        }

        $this->renderTree($output, $config, $format);
        return Command::SUCCESS;
    }
}
