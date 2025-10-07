<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: self::NAME,
    description: 'Run php a script after a full Drupal bootstrap.',
    aliases: ['scr', 'php-script'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Script])]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\Formatter(defaultFormatter: 'var_dump')]
final class PhpScriptCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'php:script';

    public function __construct(
        protected readonly BootstrapManager $bootstrapManager,
        protected readonly DrushConfig $drushConfig,
        protected FormatterManager $formatterManager,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->addArgument('extra', InputArgument::IS_ARRAY, 'Script name and arguments. First argument is script name, remaining are passed to the script.')
            ->addOption('script-path', null, InputOption::VALUE_REQUIRED, 'Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'var_export')
            ->addUsage('php:script example --script-path=/path/to/scripts:/another/path')
            ->addUsage('php:script -')
            ->addUsage('php:script foo -- apple --cider')
            ->setHelp('A useful alternative to eval command when your php is lengthy or you can\'t be bothered to figure out bash quoting. If you plan to share a script with others, consider making a full Drush command instead, since that\'s more self-documenting. Drush provides commandline options to the script via a variable called $extra.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return is_int($data) ? $data : self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

        $found = false;
        $all = [];
        $extra = $input->getArgument('extra');
        $script = array_shift($extra);

        if ($script == '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // If testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $data = stream_get_contents($inputStream);
            return $data;
        } elseif ($script && file_exists($script)) {
            $found = $script;
        } else {
            // Array of paths to search for scripts
            $searchpath['cwd'] = $this->drushConfig->cwd();

            // Additional script paths, specified by 'script-path' option
            if ($script_path = $input->getOption('script-path')) {
                foreach (explode(PATH_SEPARATOR, $script_path) as $path) {
                    $searchpath[] = $path;
                }
            }
            $this->logger->notice('Searching for scripts in {paths}', ['paths' => implode(',', $searchpath)]);

            if (empty($script)) {
                $all = [];
                // List all available scripts.
                $files = Finder::create()
                    ->files()
                    ->name('*.php')
                    ->depth(0)
                    ->in($searchpath);
                foreach ($files as $file) {
                    $all[] = $file->getRelativePathname();
                }
                return implode("\n", $all);
            } else {
                // Execute the specified script.
                foreach ($searchpath as $path) {
                    $script_filename = $path . '/' . $script;
                    if (file_exists($script_filename . '.php')) {
                        $script_filename .= '.php';
                    }
                    if (file_exists($script_filename)) {
                        $found = $script_filename;
                        break;
                    }
                    $all[] = $script_filename;
                }
                if (!$found) {
                    throw new \Exception(dt('Unable to find any of the following: @files', ['@files' => implode(', ', $all)]));
                }
            }
        }

        $return = include($found);
        // 1 just means success so don't return it.
        // http://us3.php.net/manual/en/function.include.php#example-120
        if ($return !== 1) {
            return $return;
        }
    }
}
