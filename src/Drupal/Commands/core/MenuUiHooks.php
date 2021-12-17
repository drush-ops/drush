<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MenuUiHooks extends DrushCommands
{
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var MenuParentFormSelectorInterface */
    protected $menuParentFormSelector;

    public function __construct(
        ModuleHandlerInterface $moduleHandler,
        MenuParentFormSelectorInterface $menuParentFormSelector
    ) {
        $this->moduleHandler = $moduleHandler;
        $this->menuParentFormSelector = $menuParentFormSelector;
    }

    /** @hook option nodetype:create */
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'menus-available',
            '',
            InputOption::VALUE_OPTIONAL,
            'The menus available to place links in for this content type.'
        );

        $command->addOption(
            'menu-default-parent',
            '',
            InputOption::VALUE_OPTIONAL,
            'The menu item to be the default parent for a new link in the content authoring form.'
        );
    }

    /** @hook on-event node-type-set-options */
    public function hookSetOptions(InputInterface $input): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $this->ensureOption('menus-available', [$this, 'askMenus'], true);
        $menus = $this->input->getOption('menus-available');
        if (is_string($menus)) {
            $menus = explode(',', $menus);
            $this->input->setOption('menus-available', $menus);
        }

        if ($menus === []) {
            return;
        }

        $this->ensureOption('menu-default-parent', [$this, 'askDefaultParent'], true);
    }

    /** @hook on-event nodetype-create */
    public function hookCreate(array &$values): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $values['third_party_settings']['menu_ui']['available_menus'] = $this->input->getOption('menus-available');
        $values['third_party_settings']['menu_ui']['parent'] = $this->input->getOption('menu-default-parent') ?? '';
        $values['dependencies']['module'][] = 'menu_ui';
    }

    protected function askMenus(): array
    {
        $menus = menu_ui_get_menus();
        $choices = ['_none' => '- None -'];

        foreach ($menus as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label;
            $choices[$name] = $label;
        }

        $question = (new ChoiceQuestion('Available menus', $choices, '_none'))
            ->setMultiselect(true);

        return array_filter(
            $this->io()->askQuestion($question) ?: [],
            function (string $value) {
                return $value !== '_none';
            }
        );
    }

    protected function askDefaultParent(): string
    {
        $menus = $this->input->getOption('menus-available');
        $menus = array_intersect_key(menu_ui_get_menus(), array_flip($menus));
        $options = $this->menuParentFormSelector->getParentSelectOptions('', $menus);

        return $this->io()->choice('Default parent item', $options, 1);
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('menu_ui');
    }

    protected function ensureOption(string $name, callable $asker, bool $required): void
    {
        $value = $this->input->getOption($name);

        if ($value === null) {
            $value = $asker();
        }

        if ($required && $value === null) {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => $name,
            ]));
        }

        $this->input->setOption($name, $value);
    }
}
