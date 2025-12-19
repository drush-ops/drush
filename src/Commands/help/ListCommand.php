<?php

declare(strict_types=1);

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\JsonDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

#[AsCommand(
    name: self::NAME,
    description: 'List available commands',
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\HelpLinks(links: [HelpLinks::Readme])]
final class ListCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'list';

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'format', mode: InputOption::VALUE_REQUIRED, description: 'Show command list in XML format', default: 'listcli')
            ->addOption(name: 'filter', mode: InputOption::VALUE_REQUIRED, description: 'Restrict command list to those commands defined in the specified category. Omit value to choose from a list of names.')
            ->addOption(name: 'raw', mode: InputOption::VALUE_NONE, description: 'Show a simple table of command names and descriptions.')
            ->addUsage('drush list --filter=devel_generate')
            ->addUsage('drush list --format=xml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);
        $io = new DrushStyle($input, $output);
        $application = $this->getApplication();
        $options = $input->getOptions();
        $all = $application->all();
        $namespaced = self::categorize($all);

        // Filter out namespaces that the user does not want to see
        $filter_category = $options['filter'];
        if (!empty($filter_category)) {
            if (!array_key_exists($filter_category, $namespaced)) {
                throw new \Exception(dt("The specified command category !filter does not exist.", ['!filter' => $filter_category]));
            }
            $namespaced = [$filter_category => $namespaced[$filter_category]];
        }

        /**
         * The listcli,json and raw formats don't yet go through the output formatter system.
         * because \Consolidation\OutputFormatters\Transformations\DomToArraySimplifier
         * can't yet handle the DomDocument that produces the Symfony expected XML. For consistency, the XML
         * output chooses to use the Symfony descriptor as well.
         */
        if ($options['raw']) {
            $this->renderListRaw($namespaced, $output);
            return self::SUCCESS;
        } elseif ($options['format'] == 'listcli') {
            $preamble = dt('Run `drush help [command]` to view command-specific help.  Run `drush topic` to read even more documentation.');
            self::renderListCLI($application, $namespaced, $output, $preamble);
            if (!$this->bootstrapManager->hasBootstrapped((DrupalBootLevels::ROOT))) {
                $io->note(dt('Drupal root not found. In order to see Drupal-specific commands, make sure that the `drush` you are calling is a dependency in your site\'s composer.json. The --uri option might also help.'));
            }
            return self::SUCCESS;
        } elseif ($options['format'] == 'xml') {
            $descriptor = new XmlDescriptor();
            $descriptor->describe($output, $application, []);
            return self::SUCCESS;
        } elseif ($options['format'] == 'json') {
            $descriptor = new JsonDescriptor();
            $descriptor->describe($output, $application, []);
            return self::SUCCESS;
        } else {
            // No longer used. Works for XML, but gives error for JSON.
            // $dom = $this->buildDom($namespaced, $application);
            // return $dom;
            return self::SUCCESS;
        }
    }

    public function buildDom($namespaced, $application): \DOMDocument
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

    public static function renderListCLI(Application $application, array $namespaced, OutputInterface $output, string $preamble): void
    {
        $output->writeln($application->getHelp());
        $output->writeln('');
        $output->writeln($preamble);
        $output->writeln('');

        $rows[] = ['Available commands:', ''];
        foreach ($namespaced as $namespace => $list) {
            $rows[] = ['<comment>' . $namespace . ':</comment>', ''];
            foreach ($list as $name => $command) {
                $description = $command->getDescription();

                // For commands such as foo:bar, remove
                // any alias 'foo-bar' from the alias list.
                $aliasList = array_filter(
                    $command->getAliases(),
                    fn($aliasName) => $aliasName != str_replace(':', '-', $name)
                );

                $aliases = implode(', ', $aliasList);
                $suffix = $aliases ? " ($aliases)" : '';
                $rows[] = ['  ' . $name . $suffix, $description];
            }
        }
        $formatterManager = new FormatterManager();
        $opts = [
            FormatterOptions::INCLUDE_FIELD_LABELS => false,
            FormatterOptions::TABLE_STYLE => 'compact',
            FormatterOptions::TERMINAL_WIDTH => self::getTerminalWidth(),
        ];
        $formatterOptions = new FormatterOptions([], $opts);

        $formatterManager->write($output, 'table', new RowsOfFields($rows), $formatterOptions);
    }

    public static function getTerminalWidth(): int
    {
        $term = new Terminal();
        return $term->getWidth();
    }

    public function renderListRaw(array $namespaced, OutputInterface $output): void
    {
        $table = new Table($output);
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
     *
     * @return array<string, array<Command>>
     */
    public static function categorize(array $all, string $separator = ':'): array
    {
        foreach ($all as $key => $command) {
            if (!in_array($key, $command->getAliases()) && !$command->isHidden()) {
                $parts = explode($separator, $key);
                $namespace = array_shift($parts);
                $namespaced[$namespace][$key] = $command;
            }
        }

        // Avoid solo namespaces.
        $namespaced['_global'] = [];
        foreach ($namespaced as $namespace => $commands) {
            if (count($commands) === 1) {
                $namespaced['_global'] += $commands;
                unset($namespaced[$namespace]);
            }
        }

        ksort($namespaced);

        // Sort inside namespaces.
        foreach ($namespaced as $key => &$items) {
            ksort($items);
        }
        return $namespaced;
    }
}
