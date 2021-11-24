<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\link\LinkItemInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class LinkHooks extends DrushCommands
{
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;

    public function __construct(
        ModuleHandlerInterface $moduleHandler
    ) {
        $this->moduleHandler = $moduleHandler;
    }

    /** @hook option field:create */
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'link-type',
            '',
            InputOption::VALUE_REQUIRED,
            'Allowed link type.'
        );

        $command->addOption(
            'allow-link-text',
            '',
            InputOption::VALUE_REQUIRED,
            'Allow link text.'
        );
    }

    /** @hook on-event field-create-set-options */
    public function hookSetOptions(InputInterface $input): void
    {
        if (
            !$this->isInstalled()
            || $input->getOption('field-type') !== 'link'
        ) {
            return;
        }

        $input->setOption(
            'link-type',
            $this->input->getOption('link-type') ?? $this->askLinkType()
        );

        $input->setOption(
            'allow-link-text',
            $this->input->getOption('allow-link-text') ?? $this->askAllowLinkText()
        );
    }

    /** @hook on-event field-create-field-config */
    public function hookFieldConfig(array $values, InputInterface $input): array
    {
        if (
            !$this->isInstalled()
            || $values['field_type'] !== 'link'
        ) {
            return $values;
        }

        $values['settings']['title'] = $input->getOption('allow-link-text');
        $values['settings']['link_type'] = $input->getOption('link-type');

        return $values;
    }

    protected function askLinkType(): int
    {
        return $this->io()->choice('Allowed link type', [
            LinkItemInterface::LINK_INTERNAL => (string) t('Internal links only'),
            LinkItemInterface::LINK_EXTERNAL => (string) t('External links only'),
            LinkItemInterface::LINK_GENERIC => (string) t('Both internal and external links'),
        ]);
    }

    protected function askAllowLinkText(): int
    {
        return $this->io()->choice('Allow link text', [
            DRUPAL_DISABLED => (string) t('Disabled'),
            DRUPAL_OPTIONAL => (string) t('Optional'),
            DRUPAL_REQUIRED => (string) t('Required'),
        ]);
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('link');
    }
}
