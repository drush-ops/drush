<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\DrupalUtil;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Information about things that may be wrong in your Drupal installation.',
    aliases: ['status-report', 'rq', 'core-requirements'],
)]
#[CLI\FieldLabels(labels: [
    'title' => 'Title',
    'severity' => 'Severity',
    'sid' => 'SID',
    'description' => 'Description',
    'value' => 'Summary',
])]
#[CLI\DefaultTableFields(fields: ['title', 'severity', 'value'])]
#[CLI\FilterDefaultField(field: 'severity')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class RequirementsCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'core:requirements';

    public function __construct(
        protected readonly ModuleHandlerInterface $moduleHandler,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Only show status report messages with a severity greater than or equal to the specified value.', -1)
            ->addOption('ignore', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of requirements to remove from output. Run with --format=yaml to see key values to use.', '')
            ->addUsage('core:requirements --severity=2')
            ->setHelp('Show all status lines from the Status Report admin page. Use --severity=2 to show only the red lines.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        include_once DRUPAL_ROOT . '/core/includes/install.inc';
        $severities = [
            REQUIREMENT_INFO => dt('Info'),
            REQUIREMENT_OK => dt('OK'),
            REQUIREMENT_WARNING => dt('Warning'),
            REQUIREMENT_ERROR => dt('Error'),
        ];

        drupal_load_updates();

        $requirements = $this->moduleHandler->invokeAll('requirements', ['runtime']);
        $runtime_requirements = $this->moduleHandler->invokeAll('runtime_requirements');
        $requirements = array_merge($requirements, $runtime_requirements);
        $this->moduleHandler->alter('requirements', $requirements);
        $this->moduleHandler->alter('runtime_requirements', $requirements);
        // If a module uses "$requirements[] = " instead of
        // "$requirements['label'] = ", then build a label from
        // the title.
        foreach ($requirements as $key => $info) {
            if (is_numeric($key)) {
                unset($requirements[$key]);
                $new_key = strtolower(str_replace(' ', '_', (string) $info['title']));
                $requirements[$new_key] = $info;
            }
        }
        $ignore_requirements = StringUtils::csvToArray($input->getOption('ignore'));
        foreach ($ignore_requirements as $ignore) {
            unset($requirements[$ignore]);
        }
        ksort($requirements);

        $min_severity = (int)$input->getOption('severity');
        $format = $input->getOption('format');
        foreach ($requirements as $key => $info) {
            // Adjust once Drupal 11.1- is unsupported.
            $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
            if (is_object($severity)) {
                $severity = $severity->value;
            }

            $rows[$key] = [
                'title' => $this->styleRow((string) $info['title'], $format, $severity),
                'value' => $this->styleRow(DrupalUtil::drushRender($info['value'] ?? ''), $format, $severity),
                'description' => $this->styleRow(DrupalUtil::drushRender($info['description'] ?? ''), $format, $severity),
                'sid' => $severity,
                'severity' => $this->styleRow(@$severities[$severity], $format, $severity)
            ];
            if ($severity < $min_severity) {
                unset($rows[$key]);
            }
        }

        return new RowsOfFields($rows ?? []);
    }

    private function styleRow($content, $format, $severity): ?string
    {
        if (
            !in_array($format, [
            'sections',
            'table',
            ])
        ) {
            return $content;
        }

        return match ($severity) {
            REQUIREMENT_OK => '<info>' . $content . '</>',
            REQUIREMENT_WARNING => '<comment>' . $content . '</>',
            REQUIREMENT_ERROR => '<fg=red>' . $content . '</>',
            default => $content,
        };
    }
}
