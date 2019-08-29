<?php
namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use Drush\Utils\StringUtils;

class DrupalCommands extends DrushCommands
{

    /**
     * @var \Drupal\Core\CronInterface
     */
    protected $cron;

    /**
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * @return \Drupal\Core\CronInterface
     */
    public function getCron()
    {
        return $this->cron;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

    /**
     * @param \Drupal\Core\CronInterface $cron
     * @param ModuleHandlerInterface $moduleHandler
     */
    public function __construct(CronInterface $cron, ModuleHandlerInterface $moduleHandler)
    {
        $this->cron = $cron;
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * Run all cron hooks in all active modules for specified site.
     *
     * @command core:cron
     * @aliases cron,core-cron
     * @topics docs:cron
     */
    public function cron()
    {
        $this->getCron()->run();
    }

    /**
     * Information about things that may be wrong in your Drupal installation.
     *
     * @command core:requirements
     * @option severity Only show status report messages with a severity greater than or equal to the specified value.
     * @option ignore Comma-separated list of requirements to remove from output. Run with --format=yaml to see key values to use.
     * @aliases status-report,rq,core-requirements
     * @usage drush core:requirements
     *   Show all status lines from the Status Report admin page.
     * @usage drush core:requirements --severity=2
     *   Show only the red lines from the Status Report admin page.
     * @table-style default
     * @field-labels
     *   title: Title
     *   severity: Severity
     *   sid: SID
     *   description: Description
     *   value: Summary
     * @default-fields title,severity,value
     * @filter-default-field severity
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function requirements($options = ['format' => 'table', 'severity' => -1, 'ignore' => ''])
    {
        include_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        $severities = [
            REQUIREMENT_INFO => dt('Info'),
            REQUIREMENT_OK => dt('OK'),
            REQUIREMENT_WARNING => dt('Warning'),
            REQUIREMENT_ERROR => dt('Error'),
        ];

        drupal_load_updates();

        $requirements = $this->getModuleHandler()->invokeAll('requirements', ['runtime']);
        // If a module uses "$requirements[] = " instead of
        // "$requirements['label'] = ", then build a label from
        // the title.
        foreach ($requirements as $key => $info) {
            if (is_numeric($key)) {
                unset($requirements[$key]);
                $new_key = strtolower(str_replace(' ', '_', $info['title']));
                $requirements[$new_key] = $info;
            }
        }
        $ignore_requirements = StringUtils::csvToArray($options['ignore']);
        foreach ($ignore_requirements as $ignore) {
            unset($requirements[$ignore]);
        }
        ksort($requirements);

        $min_severity = $options['severity'];
        $i = 0;
        foreach ($requirements as $key => $info) {
            $info += ['value' => '', 'description' => ''];
            $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
            $rows[$i] = [
                'title' => (string) $info['title'],
                'value' => (string) $info['value'],
                'description' => DrupalUtil::drushRender($info['description']),
                'sid' => $severity,
                'severity' => @$severities[$severity]
            ];
            if ($severity < $min_severity) {
                unset($rows[$i]);
            }
            $i++;
        }
        $result = new RowsOfFields($rows);
        return $result;
    }
}
