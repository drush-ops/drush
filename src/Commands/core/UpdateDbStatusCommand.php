<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Update\UpdateRegistry;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Log\DrushLoggerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'List any pending database updates.',
    aliases: ['updbst', 'updatedb-status'],
)]
// Manage own bootstrap in initialize().
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\FieldLabels(labels: [
    'module' => 'Module',
    'update_id' => 'Update ID',
    'description' => 'Description',
    'type' => 'Type'
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
        protected readonly DrushLoggerManager $logger,
    ) {
        parent::__construct();
    }

    /**
     * Bootstrap using a custom kernel. Replaces the [#Kernel] attribute.
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $annotationData = new AnnotationData(['kernel' => 'update']);
        $this->bootstrapManager->bootstrapToPhaseIndex(DrupalBootLevels::FULL, $annotationData);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): ?RowsOfFields
    {
        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        drupal_load_updates();
        [$pending, $start, $warnings] = $this->getUpdatedbStatus();

        // Output any warnings.
        $return = null;
        foreach ($warnings as $module => $warning) {
            $this->logger->warning(dt('!module: !warning', ['!module' => $module, '!warning' => $warning]));
        }
        if (empty($pending)) {
            $this->logger->success(dt("No database updates required."));
        } else {
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

        $return = [];
        $warnings = [];

        // Ensure system module's updates run first.
        $start['system'] = [];

        foreach ($pending as $module => $updates) {
            if (isset($updates['start'])) {
                $start[$module] = $updates['start'];
                foreach ($updates['pending'] as $update_id => $description) {
                    // Strip cruft from front.
                    $description = str_replace($update_id . ' -   ', '', $description);
                    $return[$module . "_update_$update_id"] = [
                        'module' => $module,
                        'update_id' => $update_id,
                        'description' => $description,
                        'type' => 'hook_update_n'
                    ];
                }
            }
            if (isset($updates['warning'])) {
                $warnings[$module] = $updates['warning'];
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
                            'type' => 'post-update'
                        ];
                    }
                }
            }
        }

        return [$return, $start, $warnings];
    }
}
