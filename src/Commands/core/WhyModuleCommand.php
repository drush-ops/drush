<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Command\OutputFormat;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
    description: 'List all objects (modules, configurations) depending on a given module.',
    aliases: ['wm'],
    usages: [
        'why:module node --type=module',
        'why:module node --type=module --no-only-installed',
        'why:module node --type=config',
        'why:module node --type=config --format=json',
    ],
)]
final class WhyModuleCommand
{
    use DependencyTreeTrait;

    public const string NAME = 'why:module';

    public function __construct(
        protected ModuleExtensionList $moduleExtensionList,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The module to check dependents for.')]
        string $module,
        #[Option('Type of dependents: module or config.', suggestedValues: ['module', 'config'])]
        ?string $type = null,
        #[Option('Only check installed modules. Use --no-only-installed to include uninstalled modules.')]
        bool $onlyInstalled = true,
        #[Option('An alternative output format such as json or yaml. Defaults to a tree.')]
        ?OutputFormat $format = null,
    ): int {
        if ($type === null || $type === '') {
            throw new \InvalidArgumentException((string) $this->t('The --type option is mandatory.'));
        }
        if (!in_array($type, ['module', 'config'], true)) {
            throw new \InvalidArgumentException((string) $this->t("The --type option can take only 'module' or 'config' as value."));
        }
        if (!$onlyInstalled && $type === 'config') {
            throw new \InvalidArgumentException((string) $this->t('Cannot use --type=config together with --no-only-installed.'));
        }

        $this->resetTree();
        if ($type === 'module') {
            $dependencies = array_map(
                fn(Extension $extension): array => array_map(
                    fn(string $dependency): string => Dependency::createFromString($dependency)->getName(),
                    $extension->info['dependencies'] ?? [],
                ),
                $this->moduleExtensionList->reset()->getList(),
            );
            if ($onlyInstalled) {
                $dependencies = array_intersect_key($dependencies, $this->moduleHandler->getModuleList());
            }
            if (!isset($dependencies[$module])) {
                throw new \InvalidArgumentException((string) $this->t('Invalid @module module.', ['@module' => $module]));
            }
            $this->buildDependents($dependencies);
        } else {
            if (!$this->moduleHandler->moduleExists($module)) {
                throw new \InvalidArgumentException((string) $this->t('Invalid @module module.', ['@module' => $module]));
            }
            $configs = $this->scanConfigs();
            $this->buildDependents($configs['module']);
            $this->buildDependents($configs['config']);
        }

        if (!$this->hasDependents($module)) {
            $message = $type === 'module'
                ? $this->t('No other module depends on @module.', ['@module' => $module])
                : $this->t('No config entity depends on @module.', ['@module' => $module]);
            // On stderr, so that json and yaml output stays parseable.
            (new SymfonyStyle($input, $output))->getErrorStyle()->note((string) $message);
            return Command::SUCCESS;
        }

        $this->renderTree($output, $module, $format);
        return Command::SUCCESS;
    }
}
