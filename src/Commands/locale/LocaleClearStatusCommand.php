<?php

declare(strict_types=1);

namespace Drush\Commands\locale;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Clears the translation status.',
    aliases: ['locale-clear-status'],
)]
#[CLI\ValidateModulesEnabled(modules: ['locale'])]
#[CLI\Version(version: '11.5')]
final class LocaleClearStatusCommand extends Command
{
    use AutowireTrait;
    use LocaleTrait;

    const string NAME = 'locale:clear-status';

    public function __construct(
        protected LanguageManagerInterface $languageManager,
        protected ConfigFactoryInterface $configFactory,
        protected ModuleHandlerInterface $moduleHandler,
        protected StateInterface $state,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        locale_translation_clear_status();

        return self::SUCCESS;
    }
}
