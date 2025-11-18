<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\Util\Escape;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Edit drush.yml, site alias, and Drupal settings.php files.',
    aliases: ['conf', 'config', 'core-edit'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
#[CLI\OptionsetGetEditor]
final class EditCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    public const string NAME = 'core:edit';

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
        private readonly DrushConfig $drushConfig,
        private readonly ProcessManager $processManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filter', InputArgument::OPTIONAL, 'A substring for filtering the list of files. Omit this argument to choose from loaded files.')
            ->addUsage('--bg core-config')
            ->addUsage('core:edit etc')
            ->addUsage('core:edit demo.alia')
            ->addUsage('core:edit sett')
            ->addUsage('core:edit --choice=2');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::FULL);

        $io = new DrushStyle($input, $output);
        $filter = $input->getArgument('filter');
        $options = $input->getOptions();

        $all = $this->load();

        // Apply any filter that was supplied.
        if ($filter) {
            foreach ($all as $file => $display) {
                if (!str_contains($file, $filter)) {
                    unset($all[$file]);
                }
            }
        }

        $editor = self::getEditor($options['editor'] ?? null);
        if (count($all) === 1) {
            $filepath = current($all);
        } else {
            $choice = $io->choice(dt("Choose a file to edit"), $all);
            $filepath = $choice;
            // We don't yet support launching editor at a start line.
            if ($pos = strpos($filepath, ':')) {
                $filepath = substr($filepath, 0, $pos);
            }
        }

        // A bit awkward due to backward compat.
        $cmd = sprintf($editor, Escape::shellArg($filepath));
        $process = $this->processManager->shell($cmd);
        $process->setTty(true);
        $process->mustRun();

        return self::SUCCESS;
    }

    public function load($headers = true): array
    {
        $php_header = $rcs_header = $aliases_header = $drupal_header = $bash_header = $drupal = [];
        $php = self::phpIniFiles();
        if ($php !== []) {
            if ($headers) {
                $php_header = ['phpini' => '-- PHP ini files --'];
            }
        }

        $bash = $this->bashFiles();
        if ($bash !== []) {
            if ($headers) {
                $bash_header = ['bash' => '-- Bash files --'];
            }
        }

        if ($rcs = $this->drushConfig->configPaths()) {
            // @todo filter out any files that are within Drush.
            $rcs = array_combine($rcs, $rcs);
            if ($headers) {
                $rcs_header = ['drushyml' => '-- drush.yml --'];
            }
        }

        if ($aliases = $this->siteAliasManager->listAllFilePaths()) {
            sort($aliases);
            $aliases = array_combine($aliases, $aliases);
            if ($headers) {
                $aliases_header = ['aliases' => '-- Aliases --'];
            }
        }

        if ($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL)) {
            $boot = $this->bootstrapManager->bootstrap();
            $site_root = $boot->getKernel()->getSitePath();
            $path = realpath($site_root . '/settings.php');
            $drupal[$path] = $path;
            if (file_exists($site_root . '/settings.local.php')) {
                $path = realpath($site_root . '/settings.local.php');
                $drupal[$path] = $path;
            }
            if ($path = realpath($this->bootstrapManager->getRoot() . '/.htaccess')) {
                $drupal[$path] = $path;
            }
            if ($headers) {
                $drupal_header = ['drupal' => '-- Drupal --'];
            }
        }

        return array_merge($php_header, $php, $bash_header, $bash, $rcs_header, $rcs, $aliases_header, $aliases, $drupal_header, $drupal);
    }

    public static function phpIniFiles(): array
    {
        $return = [];
        if ($file = php_ini_loaded_file()) {
            $return = [$file];
        }
        return $return;
    }

    public function bashFiles(): array
    {
        $bashFiles = [];
        $home = $this->drushConfig->home();
        if ($bashrc = self::findBashrc($home)) {
            $bashFiles[$bashrc] = $bashrc;
        }
        return $bashFiles;
    }

    /**
     * Determine which .bashrc file is best to use on this platform.
     *
     */
    public static function findBashrc(string $home): string
    {
        return $home . "/.bashrc";
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('filter')) {
            $suggestions->suggestValues($this->load(false));
        }
    }
}
