<?php

namespace Drush\Preflight;

use Symfony\Component\Console\Input\InputInterface;
use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;
use Drush\Symfony\DrushArgvInput;
use Drush\Utils\StringUtils;
use Drush\Symfony\LessStrictArgvInput;

/**
 * Storage for arguments preprocessed during preflight.
 *
 * Holds @sitealias, if present, and a limited number of global options.
 *
 * TODO: The methods here with >~3 lines of logic could be refactored into a couple
 * of different classes e.g. a helper to convert preflight args to configuration,
 * and another to prepare the input object.
 */
class PreflightArgs extends Config implements PreflightArgsInterface
{
    /**
     * @var array $args Remaining arguments not handled by the preprocessor
     */
    protected $args;

    /**
     * @var string $homeDir Path to directory to use when replacing ~ in paths
     */
    protected $homeDir;

    protected $commandName;

    public function homeDir(): string
    {
        return $this->homeDir;
    }

    public function setHomeDir(string $homeDir): void
    {
        $this->homeDir = $homeDir;
    }

    const DRUSH_CONFIG_PATH_NAMESPACE = 'drush.paths';

    const DRUSH_RUNTIME_CONTEXT_NAMESPACE = 'runtime.contxt';

    const ALIAS = 'alias';

    const ALIAS_PATH = 'alias-path';

    const COMMAND_PATH = 'include';

    const CONFIG_PATH = 'config';

    const COVERAGE_FILE = 'coverage-file';

    const LOCAL = 'local';

    const ROOT = 'root';

    const URI = 'uri';

    const SIMULATE = 'simulate';

    const STRICT = 'strict';

    const DEBUG = 'preflight-debug';

    /**
     * PreflightArgs constructor
     *
     * @param array $data Initial data (not usually used)
     */
    public function __construct($data = [])
    {
        parent::__construct($data + [self::STRICT => true]);
    }

    /**
     * @inheritdoc
     */
    public function optionsWithValues(): array
    {
        return [
            '-r=' => 'setSelectedSite',
            '--root=' => 'setSelectedSite',
            '--debug' => 'setDebug',
            '-d' => 'setDebug',
            '-vvv' => 'setDebug',
            '-l=' => 'setUri',
            '--uri=' => 'setUri',
            '-c=' => 'addConfigPath',
            '--config=' => 'addConfigPath',
            '--alias-path=' => 'addAliasPath',
            '--include=' => 'addCommandPath',
            '--local' => 'setLocal',
            '--simulate' => 'setSimulate',
            '-s' => 'setSimulate',
            '--drush-coverage=' => 'setCoverageFile',
            '--strict=' => 'setStrict',
            '--help' => 'adjustHelpOption',
            '-h' => 'adjustHelpOption',
        ];
    }

    /**
     * If the user enters '--help' or '-h', thrown that
     * option away and add a 'help' command to the beginning
     * of the argument list.
     */
    public function adjustHelpOption(): void
    {
        $drushPath = array_shift($this->args);
        array_unshift($this->args, $drushPath, 'help');
    }

    /**
     * Map of option key to the corresponding config key to store the
     * preflight option in. The values of the config items in this map
     * must be BOOLEANS or STRINGS.
     */
    protected function optionConfigMap(): array
    {
        return [
            self::SIMULATE => \Robo\Config\Config::SIMULATE,
            self::LOCAL => self::DRUSH_RUNTIME_CONTEXT_NAMESPACE . '.' . self::LOCAL,
        ];
    }

    /**
     * Map of path option keys to the corresponding config key to store the
     * preflight option in. The values of the items in this map must be
     * STRINGS or ARRAYS OF STRINGS.
     */
    protected function optionConfigPathMap(): array
    {
        return [
            self::ALIAS_PATH => self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::ALIAS_PATH,
            self::CONFIG_PATH => self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::CONFIG_PATH,
            self::COMMAND_PATH => self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::COMMAND_PATH,
        ];
    }

    /**
     * @inheritdoc
     *
     * @see Environment::exportConfigData(), which also exports information to config.
     */
    public function applyToConfig(ConfigInterface $config): void
    {
        // Copy the relevant preflight options to the applicable configuration namespace
        foreach ($this->optionConfigMap() as $option_key => $config_key) {
            $config->set($config_key, $this->get($option_key));
        }
        // Merging as they are lists.
        foreach ($this->optionConfigPathMap() as $option_key => $config_key) {
            $cli_paths = $this->get($option_key, []);
            $config_paths = (array)$config->get($config_key, []);

            $merged_paths = array_unique(array_merge($cli_paths, $config_paths));
            $config->set($config_key, $merged_paths);
            $this->set($option_key, $merged_paths);
        }

        // Store the runtime arguments and options (sans the runtime context items)
        // in runtime.argv et. al.
        $config->set('runtime.drush-script', $this->applicationPath());
        $config->set('runtime.command', $this->commandName() ?: 'help');
        $config->set('runtime.argv', $this->args());
        $config->set('runtime.options', $this->getOptionNameList($this->args()));
    }

    /**
     * @inheritdoc
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * @inheritdoc
     */
    public function applicationPath()
    {
        return realpath(reset($this->args));
    }

    /**
     * @inheritdoc
     */
    public function commandName()
    {
        return $this->commandName;
    }

    /**
     * @inheritdoc
     */
    public function setCommandName($commandName): void
    {
        $this->commandName = $commandName;
    }

    /**
     * @inheritdoc
     */
    public function addArg($arg): self
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function passArgs($args): self
    {
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function alias()
    {
        return $this->get(self::ALIAS);
    }

    /**
     * @inheritdoc
     */
    public function hasAlias(): bool
    {
        return $this->has(self::ALIAS);
    }

    /**
     * @inheritdoc
     */
    public function setAlias($alias): self
    {
        // Treat `drush @self ...` as if an alias had not been used at all.
        if ($alias == '@self') {
            $alias = '';
        }
        return $this->set(self::ALIAS, $alias);
    }

    /**
     * Get the selected site. Here, the default will typically be the cwd.
     */
    public function selectedSite($default = false)
    {
        return $this->get(self::ROOT, $default);
    }

    public function setDebug($value): void
    {
        $this->set(self::DEBUG, $value);
        $this->addArg('-vvv');
    }

    /**
     * Set the selected site.
     */
    public function setSelectedSite($root): self
    {
        return $this->set(self::ROOT, StringUtils::replaceTilde($root, $this->homeDir()));
    }

    /**
     * Get the selected uri
     */
    public function uri($default = false)
    {
        return $this->get(self::URI, $default);
    }

    public function hasUri(): bool
    {
        return $this->has(self::URI);
    }

    /**
     * Set the uri option
     */
    public function setUri($uri): self
    {
        return $this->set(self::URI, $uri);
    }

    /**
     * Get the config path where drush.yml files may be found
     */
    public function configPaths()
    {
        return $this->get(self::CONFIG_PATH, []);
    }

    /**
     * Add another location where drush.yml files may be found
     */
    public function addConfigPath(string $path): self
    {
        $paths = $this->configPaths();
        $paths[] = StringUtils::replaceTilde($path, $this->homeDir());
        return $this->set(self::CONFIG_PATH, $paths);
    }

    /**
     * Add multiple additional locations where drush.yml files may be found.
     *
     * @param string[] $configPaths
     */
    public function mergeConfigPaths(array $configPaths): self
    {
        $paths = $this->configPaths();
        $merged_paths = array_merge($paths, $configPaths);
        return $this->set(self::CONFIG_PATH, $merged_paths);
    }

    /**
     * Get the alias paths where drush site.site.yml files may be found
     */
    public function aliasPaths()
    {
        return $this->get(self::ALIAS_PATH, []);
    }

    /**
     * Set one more path where aliases may be found.
     */
    public function addAliasPath(string $path): self
    {
        $paths = $this->aliasPaths();
        $paths[] = StringUtils::replaceTilde($path, $this->homeDir());
        return $this->set(self::ALIAS_PATH, $paths);
    }

    /**
     * Add multiple additional locations for alias paths.
     */
    public function mergeAliasPaths(string $aliasPaths): self
    {
        $aliasPaths = array_map(
            function ($item) {
                return StringUtils::replaceTilde($item, $this->homeDir());
            },
            $aliasPaths
        );
        $paths = $this->aliasPaths();
        $merged_paths = array_merge($paths, $aliasPaths);
        return $this->set(self::ALIAS_PATH, $merged_paths);
    }

    /**
     * Get the path where Drush commandfiles e.g. FooCommands.php may be found.
     */
    public function commandPaths()
    {
        return $this->get(self::COMMAND_PATH, []);
    }

    /**
     * Add one more path where commandfiles might be found.
     */
    public function addCommandPath(string $path): self
    {
        $paths = $this->commandPaths();
        $paths[] = StringUtils::replaceTilde($path, $this->homeDir());
        return $this->set(self::COMMAND_PATH, $paths);
    }

    /**
     * Add multiple paths where commandfiles might be found.
     *
     * @param $commanPaths
     */
    public function mergeCommandPaths($commandPaths): self
    {
        $paths = $this->commandPaths();
        $merged_paths = array_merge($paths, $commandPaths);
        return $this->set(self::COMMAND_PATH, $merged_paths);
    }

    /**
     * Determine whether Drush is in "local" mode
     */
    public function isLocal()
    {
        return $this->get(self::LOCAL, false);
    }

    /**
     * Set local mode
     */
    public function setLocal(bool $isLocal): self
    {
        return $this->set(self::LOCAL, $isLocal);
    }

    /**
     * Determine whether Drush is in "simulated" mode.
     */
    public function isSimulated()
    {
        return $this->get(self::SIMULATE);
    }

    /**
     * Set simulated mode
     *
     * @param bool $simulated
     */
    public function setSimulate($simulate): self
    {
        return $this->set(self::SIMULATE, $simulate);
    }

    /**
     * Get the path to the coverage file.
     */
    public function coverageFile()
    {
        return $this->get(self::COVERAGE_FILE);
    }

    /**
     * Set the coverage file path.
     *
     * @param string
     */
    public function setCoverageFile($coverageFile): self
    {
        return $this->set(self::COVERAGE_FILE, StringUtils::replaceTilde($coverageFile, $this->homeDir()));
    }

    /**
     * Determine whether Drush is in "strict" mode or not.
     */
    public function isStrict()
    {
        return $this->get(self::STRICT);
    }

    /**
     * Set strict mode.
     */
    public function setStrict(bool $strict): self
    {
        return $this->set(self::STRICT, $strict);
    }

    /**
     * Search through the provided argv list, and return
     * just the option name of any item that is an option.
     *
     * @param array $argv e.g. ['foo', '--bar=baz', 'boz']
     *
     * @return string[] e.g. ['bar']
     */
    protected function getOptionNameList(array $argv): array
    {
        return array_filter(
            array_map(
                function ($item) {
                    // Ignore configuration definitions
                    if (substr($item, 0, 2) == '-D') {
                        return null;
                    }
                    // Regular expression matches:
                    //   ^-+        # anything that begins with one or more '-'
                    //   ([^= ]*)   # any number of characters up to the first = or space
                    if (preg_match('#^-+([^= ]*)#', $item, $matches)) {
                        return $matches[1];
                    }
                },
                $argv
            )
        );
    }

    /**
     * Create a Symfony Input object.
     */
    public function createInput(): InputInterface
    {
        // In strict mode (the default), create an ArgvInput. When
        // strict mode is disabled, create a more forgiving input object.
        if ($this->isStrict()) {
            return new DrushArgvInput($this->args());
        }
        return new LessStrictArgvInput($this->args());
    }
}
