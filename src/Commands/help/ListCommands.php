<?php
namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommands extends DrushCommands
{
    /**
     * List available commands.
     *
     * @command list
     * @option filter Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.
     * @option raw Show a simple table of command names and descriptions.
     * @bootstrap DRUSH_BOOTSTRAP_MAX
     * @usage drush list
     *   List all commands.
     * @usage drush list --filter=devel_generate
     *   Show only commands starting with devel-
     * @usage drush list --format=xml
     *   List all commands in Symfony compatible xml format.
     *
     * @return \DOMDocument
     */
    public function helpList($filter, $options = ['format' => 'listcli', 'raw' => false, 'filter' => null])
    {
        $application = Drush::getApplication();
        annotation_adapter_add_legacy_commands_to_application($application);
        $all = $application->all();
        $namespaced = $this->categorize($all);

        // Filter out namespaces that the user does not want to see
        $filter_category = $options['filter'];
        if (!empty($filter_category)) {
            if (!array_key_exists($filter_category, $namespaced)) {
                throw new \Exception(dt("The specified command category !filter does not exist.", array('!filter' => $filter_category)));
            }
            $namespaced = array($filter_category => $namespaced[$filter_category]);
        }

        /**
         * The listcli and raw formats don't yet go through the output formatter system.
         * because \Consolidation\OutputFormatters\Transformations\DomToArraySimplifier
         * can't yet handle the DomDocument that produces the Symfony expected XML.
         */
        if ($options['raw']) {
            $this->renderListRaw($namespaced);
            return null;
        } elseif ($options['format'] == 'listcli') {
            $preamble = dt('Run `drush help [command]` to view command-specific help.  Run `drush topic` to read even more documentation.');
            $this->renderListCLI($application, $namespaced, $this->output(), $preamble);
            if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_ROOT)) {
                $this->io()->note(dt('Drupal root not found. Pass --root or a @siteAlias in order to see Drupal-specific commands.'));
            }
            return null;
        } else {
            $dom = $this->buildDom($namespaced, $application);
            return $dom;
        }
    }

    /**
     * @param $namespaced
     * @param $application
     * @return \DOMDocument
     */
    public function buildDom($namespaced, $application)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $rootXml = $dom->createElement('symfony');
        $rootXml->setAttribute('name', $application->getName());
        if ($application->getVersion() !== 'UNKNOWN') {
            $rootXml->setAttribute('version', $application->getVersion());
        }


        // Create two top level  elements.
        $commandsXML = $dom->createElement('commands');
        $namespacesXML = $dom->createElement('namespaces');

        foreach ($namespaced as $namespace => $commands) {
            $namespaceXML = $dom->createElement('namespace');
            $namespaceXML->setAttribute('id', $namespace);
            foreach ($commands as $key => $command) {
                $helpDocument = new HelpDocument($command);
                $domData = $helpDocument->getDomData();
                $node = $domData->getElementsByTagName("command")->item(0);
                $element = $dom->importNode($node, true);
                $commandsXML->appendChild($element);

                $ncommandXML = $dom->createElement('command', $key);
                $namespaceXML->appendChild($ncommandXML);
            }
            $namespacesXML->appendChild($namespaceXML);
        }

        // Append top level elements to root element in correct order.
        $rootXml->appendChild($commandsXML);
        $rootXml->appendChild($namespacesXML);
        $dom->appendChild($rootXml);
        return $dom;
    }

    /**
     * @param \Symfony\Component\Console\Application $application
     * @param array $namespaced
     * @param OutputInterface $output
     * @param string $preamble
     */
    public static function renderListCLI($application, $namespaced, $output, $preamble)
    {
        $output->writeln($application->getHelp());
        $output->writeln('');
        $output
        ->writeln($preamble);
        $output->writeln('');

        // For now ,this table does not need TableFormatter.
        $table = new Table($output);
        $table->setStyle('compact');
        $global_options_help = drush_get_global_options(true);
        $options = $application->getDefinition()->getOptions();
        // Only display this table for Drush help, not 'generate' command.
        if ($application->getName() == 'Drush Commandline Tool') {
            $table->addRow([new TableCell('Global options. See `drush topic core-global-options` for the full list.', array('colspan' => 2))]);
            foreach ($global_options_help as $key => $help) {
                $data = [
                    'name' => '--' . $options[$key]->getName(),
                    'description' => $help['description'],
                    // Not using $options[$key]->getDescription() as description is too long for -v
                    'accept_value' => $options[$key]->acceptValue(),
                    'is_value_required' => $options[$key]->isValueRequired(),
                    'shortcut' => $options[$key]->getShortcut(),
                ];
                $table->addRow([
                    HelpCLIFormatter::formatOptionKeys($data),
                    HelpCLIFormatter::formatOptionDescription($data)
                ]);
            }
            $table->addRow(['', '']);
            $table->render();
        }

        $rows[] = ['Available commands:', ''];
        foreach ($namespaced as $namespace => $list) {
            $rows[] = [$namespace . ':', ''];
            foreach ($list as $name => $command) {
                $description = $command->getDescription();
                $aliases = implode(', ', $command->getAliases());
                $suffix = $aliases ? " ($aliases)" : '';
                $rows[] = ['  ' . $name . $suffix, $description];
            }
        }
        $formatterManager = new FormatterManager();
        list($terminalWidth,) = $application->getTerminalDimensions();
        $opts = [
            FormatterOptions::INCLUDE_FIELD_LABELS => false,
            FormatterOptions::TABLE_STYLE => 'compact',
            FormatterOptions::TERMINAL_WIDTH => $terminalWidth,
        ];
        $formatterOptions = new FormatterOptions([], $opts);

        $formatterManager->write($output, 'table', new RowsOfFields($rows), $formatterOptions);
    }

    public function getTerminalWidth()
    {
        // From \Consolidation\AnnotatedCommand\Options\PrepareTerminalWidthOption::getTerminalWidth
        $application = Drush::getApplication();
        $dimensions = $application->getTerminalDimensions();
        if ($dimensions[0] == null) {
            return 0;
        }
        return $dimensions[0];
    }

    /**
     * @param array $namespaced
     */
    public function renderListRaw($namespaced)
    {
        $table = new Table($this->output());
        $table->setStyle('compact');
        foreach ($namespaced as $namespace => $commands) {
            foreach ($commands as $command) {
                $table->addRow([$command->getName(), $command->getDescription()]);
            }
        }
        $table->render();
    }

    /**
     * @param Command[] $all
     * @return array
     */
    public static function categorize($all)
    {
        foreach ($all as $key => $command) {
            $hidden = method_exists($command, 'getAnnotationData') && $command->getAnnotationData()->has('hidden');
            if (!in_array($key, $command->getAliases()) && !$hidden) {
                $parts = explode('-', $key);
                $namespace = count($parts) >= 2 ? array_shift($parts) : '_global';
                $namespaced[$namespace][$key] = $command;
            }
        }

        // Avoid solo namespaces.
        foreach ($namespaced as $namespace => $commands) {
            if (count($commands) == 1) {
                $namespaced['_global'] += $commands;
                unset($namespaced[$namespace]);
            }
        }

        ksort($namespaced);
        return $namespaced;
    }
}
