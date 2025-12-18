<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\link\LinkItemInterface;
use Drupal\link\LinkTitleVisibility;
use Drush\Commands\field\FieldCreateCommand;
use Drush\Commands\IoTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Event\FieldCreateFieldConfigValuesEvent;
use Drush\Event\FieldCreateInputOptionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(method: 'onConsoleDefinitionEvent')]
#[AsEventListener(method: 'onFieldConfigValues')]
#[AsEventListener(method: 'onInputOptions')]
final class CreateLinkFieldListener
{
    use IoTrait;

    public function onConsoleDefinitionEvent(ConsoleDefinitionsEvent $event): void
    {
        $application = $event->getApplication();
        if (!$application->has(FieldCreateCommand::NAME)) {
            return;
        }

        $command = $application->get(FieldCreateCommand::NAME);

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

    public function onFieldConfigValues(FieldCreateFieldConfigValuesEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());
        $values = $event->getValues();

        if ($values['field_type'] !== 'link') {
            return;
        }

        $values['settings']['title'] = $this->input->getOption('allow-link-text');
        $values['settings']['link_type'] = $this->input->getOption('link-type');

        $event->setValues($values);
    }

    public function onInputOptions(FieldCreateInputOptionsEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());

        if ($this->input->getOption('field-type') !== 'link') {
            return;
        }

        $this->input->setOption(
            'link-type',
            $this->input->getOption('link-type') ?? $this->askLinkType()
        );

        $this->input->setOption(
            'allow-link-text',
            $this->input->getOption('allow-link-text') ?? $this->askAllowLinkText()
        );
    }

    protected function askLinkType(): int
    {
        $choice = $this->io()->choice('Allowed link type', [
            LinkItemInterface::LINK_INTERNAL => (string) t('Internal links only'),
            LinkItemInterface::LINK_EXTERNAL => (string) t('External links only'),
            LinkItemInterface::LINK_GENERIC => (string) t('Both internal and external links'),
        ]);
        return (int) $choice;
    }

    protected function askAllowLinkText(): int
    {
        if (class_exists(LinkTitleVisibility::class)) {
            $options = LinkTitleVisibility::asOptions();
        } else {
            $options = [
                DRUPAL_DISABLED => (string) t('Disabled'),
                DRUPAL_OPTIONAL => (string) t('Optional'),
                DRUPAL_REQUIRED => (string) t('Required'),
            ];
        }

        $choice = $this->io()->choice('Allow link text', $options);
        return array_search($choice, $options, true);
    }
}
