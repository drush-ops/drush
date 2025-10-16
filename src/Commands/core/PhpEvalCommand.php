<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Evaluate arbitrary php code after bootstrapping Drupal (if available).',
    aliases: ['eval', 'ev', 'php-eval'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(defaultFormatter: 'var_dump')]
final class PhpEvalCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'php:eval';

    public function __construct(
        protected readonly BootstrapManager $bootstrapManager,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'PHP code. If shell escaping gets too tedious, consider using the php:script command.')
            ->addUsage("php:eval '\$node = \Drupal\node\Entity\Node::load(1); print \$node->getTitle();'")
            ->addUsage('php:eval "\Drupal::service(\'file_system\')->copy(\'$HOME/Pictures/image.jpg\', \'public://image.jpg\');"')
            ->addUsage('php:eval "node_access_rebuild();"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): mixed
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

        return eval($input->getArgument('code') . ';');
    }
}
