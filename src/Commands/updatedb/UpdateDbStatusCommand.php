<?php

namespace Drush\Commands\updatedb;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Update\UpdateRegistry;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'List any pending database updates.',
    aliases: ['updbst', 'updatedb-status'],
)]
// Manage own bootstrap in execute().
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\FieldLabels(labels: [
    'module' => 'Module',
    'update_id' => 'Update ID',
    'description' => 'Description',
    'type' => 'Type',
    'allowed' => 'Allowed',
])]
#[CLI\DefaultTableFields(fields: ['module', 'update_id', 'type', 'description'])]
#[CLI\FilterDefaultField(field: 'type')]
class UpdateDbStatusCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'updatedb:status';

    public function __construct(
        protected BootstrapManager $bootstrapManager,
        protected readonly FormatterManager $formatterManager,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do own bootstrap so we can use the 'update' kernel. Replaces the [#Kernel] attribute.
        $annotationData = new AnnotationData(['kernel' => 'update']);
        $this->bootstrapManager->bootstrapToPhaseIndex(DrupalBootLevels::FULL, $annotationData);

        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): ?RowsOfFields
    {
        require_once $this->bootstrapManager->getRoot() . '/core/includes/install.inc';
        drupal_load_updates();
        [$pending, $start, $warnings] = $this->getUpdatedbStatus();

        // Output any warnings.
        $return = null;
        foreach ($warnings as $module => $warning) {
            $this->logger->warning('{module}: {warning}', ['module' => $module, 'warning' => $warning]);
        }
        if (empty($pending)) {
            (new DrushStyle($input, $output))->success('No database updates required.');
        } else {
            if (empty($input->getOption('filter'))) {
                // Show only allowed updates by default.
                // Would ideally set a default filter in the parameter, however
                // there's an upstream issue in
                // https://github.com/consolidation/filter-via-dot-access-data/pull/51
                // that needs to be addressed before that's possible, so
                // manually handling the filter for now.
                $pending = array_filter($pending, static function ($update_hook) {
                    return empty($update_hook['allowed']) || $update_hook['allowed'] === 'yes';
                });
            }
            $return = new RowsOfFields($pending);
        }
        return $return;
    }

    /**
     * Returns information about available module updates.
     *
     * @return array
     *   An indexed array (aka tuple) with 3 elements:
     *  - An array where each item is a 4 item associative array describing a
     *    pending update.
     *  - An array listing the first update to run, keyed by module.
     *  - An array listing the available warnings, keyed by module.
     */
    public function getUpdatedbStatus(): array
    {
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        $pending = \update_get_update_list();

        $start = $this->getUpdateList();
        // Resolve any update dependencies to determine the actual updates that will
        // be run and the order they will be run in.
        $upcoming_updates = update_resolve_dependencies($start);

        $return = [];
        $warnings = [];

        // Ensure system module's updates run first.
        $start['system'] = [];

        foreach ($upcoming_updates as $upcoming_update) {
            $module = $upcoming_update['module'];
            $update_id = $upcoming_update['number'];
            $description = $pending[$module]['pending'][$update_id];
            // Strip cruft from front.
            $description = str_replace($update_id . ' -   ', '', $description);
            $module_update_function = $module . "_update_$update_id";
            $return[$module_update_function] = [
                'module' => $module,
                'update_id' => $update_id,
                'description' => $description,
                'type' => 'hook_update_n',
                'allowed' => !empty($upcoming_update['allowed']) ? 'yes' : 'no',
            ];
            if (empty($upcoming_update['allowed'])) {
                if ($upcoming_update['missing_dependencies']) {
                    // This should rarely happen, but the user should be notified
                    // since skipping them can potentially put the database in an
                    // inconsistent state.
                    $missing_warning = dt('Skipping @update_function due to missing dependencies: @missing_dependencies.', [
                        '@update_function' => "{$module_update_function}()",
                        '@missing_dependencies' => implode('(), ', $upcoming_update['missing_dependencies']) . '()',
                    ]);
                } else {
                    $missing_warning = dt("Skipping @update_function due to an error in the module's code.", [
                        '@update_function' => "{$module_update_function}()",
                    ]);
                }
                if (isset($pending[$module]['warning'])) {
                    $pending[$module]['warning'] .= "\n$missing_warning";
                } else {
                    $pending[$module]['warning'] = $missing_warning;
                }
            }
            if (isset($pending[$module]['warning'])) {
                $warnings[$module] = $pending[$module]['warning'];
            }
        }

        // Pending hook_post_update_X() implementations.
        /** @var UpdateRegistry $post_update_registry */
        $post_update_registry = \Drupal::service('update.post_update_registry');
        $post_updates = $post_update_registry->getPendingUpdateInformation();
        foreach ($post_updates as $module => $post_update) {
            foreach ($post_update as $key => $list) {
                if ($key == 'pending') {
                    foreach ($list as $id => $item) {
                        $return[$module . '-post-' . $id] = [
                            'module' => $module,
                            'update_id' => $id,
                            'description' => trim($item),
                            'type' => 'post-update',
                            'allowed' => 'yes',
                        ];
                    }
                }
            }
        }

        return [$return, $start, $warnings];
    }

    // Copy of protected \Drupal\system\Controller\DbUpdateController::getModuleUpdates.
    protected function getUpdateList(): array
    {
        $return = [];
        $updates = update_get_update_list();
        foreach ($updates as $module => $update) {
            $return[$module] = $update['start'];
        }

        return $return;
    }
}
