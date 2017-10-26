<?php
namespace Drush\Preflight;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;

use Symfony\Component\Console\Input\ArgvInput;
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
     * @var $args Remaining arguments not handled by the preprocessor
     */
    protected $args;

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
    const BACKEND = 'backend';
    const STRICT = 'strict';

    /**
     * PreflightArgs constroctor
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
    public function optionsWithValues()
    {
        return [
            '-r=' => 'setSelectedSite',
            '--root=' => 'setSelectedSite',
            '-l=' => 'setUri',
            '--uri=' => 'setUri',
            '-c=' => 'addConfigPath',
            '--config=' => 'addConfigPath',
            '--alias-path=' => 'addAliasPath',
            '--include=' => 'addCommandPath',
            '--local' => 'setLocal',
            '--simulate' => 'setSimulate',
            '-s' => 'setSimulate',
            '--backend' => 'setBackend',
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
    public function adjustHelpOption()
    {
        $drushPath = array_shift($this->args);
        array_unshift($this->args, $drushPath, 'help');
    }

    /**
     * Map of option key to the corresponding config key to store the
     * preflight option in.
     */
    protected function optionConfigMap()
    {
        return [
            self::SIMULATE =>       \Robo\Config\Config::SIMULATE,
            self::BACKEND =>        self::BACKEND,
            self::LOCAL =>          self::DRUSH_RUNTIME_CONTEXT_NAMESPACE . '.' . self::LOCAL,
        ];
    }

    /**
     * Map of path option keys to the corresponding config key to store the
     * preflight option in.
     */
    protected function optionConfigPathMap()
    {
        return [
            self::ALIAS_PATH =>     self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::ALIAS_PATH,
            self::CONFIG_PATH =>    self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::CONFIG_PATH,
            self::COMMAND_PATH =>   self::DRUSH_CONFIG_PATH_NAMESPACE . '.' . self::COMMAND_PATH,
        ];
    }

    /**
     * @inheritdoc
     *
     * @see Environment::exportConfigData(), which also exports information to config.
     */
    public function applyToConfig(ConfigInterface $config)
    {
        // Copy the relevant preflight options to the applicable configuration namespace
        foreach ($this->optionConfigMap() as $option_key => $config_key) {
            $config->set($config_key, $this->get($option_key));
        }
        // Merging as they are lists.
        foreach ($this->optionConfigPathMap() as $option_key => $config_key) {
            $cli_paths = $this->get($option_key, []);
            $config_paths = (array) $config->get($config_key, []);
            $merged_paths = array_merge($cli_paths, $config_paths);
            $config->set($config_key, $merged_paths);
            $this->set($option_key, $merged_paths);
        }

        // Store the runtime arguments and options (sans the runtime context items)
        // in runtime.argv et. al.
        $config->set('runtime.argv', $this->args());
        $config->set('runtime.options', $this->getOptionNameList($this->args()));
    }

    /**
     * @inheritdoc
     */
    public function args()
    {
        return $this->args;
    }

    /**
     * @inheritdoc
     */
    public function applicationPath()
    {
        return reset($this->args);
    }

    /**
     * @inheritdoc
     */
    public function addArg($arg)
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function passArgs($args)
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
    public function hasAlias()
    {
        return $this->has(self::ALIAS);
    }

    /**
     * @inheritdoc
     */
    public function setAlias($alias)
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

    /**
     * Set the selected site.
     */
    public function setSelectedSite($root)
    {
        return $this->set(self::ROOT, $root);
    }

    /**
     * Get the selected uri
     */
    public function uri($default = false)
    {
        return $this->get(self::URI, $default);
    }

    /**
     * Set the uri option
     */
    public function setUri($uri)
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
     *
     * @param string $path
     */
    public function addConfigPath($path)
    {
        $paths = $this->configPaths();
        $paths[] = $path;
        return $this->set(self::CONFIG_PATH, $paths);
    }

    /**
     * Add multiple additinoal locations where drush.yml files may be found
     *
     * @param string[] $configPaths
     */
    public function mergeConfigPaths($configPaths)
    {
        $paths = $this->configPaths();
        $merged_paths = array_merge($paths, $configPaths);
        return $this->set(self::CONFIG_PATH, $merged_paths);
    }

    /**
     * Get the alias paths where drush site.alias.yml files may be found
     */
    public function aliasPaths()
    {
        return $this->get(self::ALIAS_PATH, []);
    }

    /**
     * Set one more path where aliases may be found
     *
     * @param string $path
     */
    public function addAliasPath($path)
    {
        $paths = $this->aliasPaths();
        $paths[] = $path;
        return $this->set(self::ALIAS_PATH, $paths);
    }

    /**
     * Add multiple additional locations for alias paths
     *
     * @param string $aliasPaths
     */
    public function mergeAliasPaths($aliasPaths)
    {
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
     *
     * @param string $path
     */
    public function addCommandPath($path)
    {
        $paths = $this->commandPaths();
        $paths[] = $path;
        return $this->set(self::COMMAND_PATH, $paths);
    }

    /**
     * Add multiple paths where commandfiles might be found
     *
     * @param $commanPaths
     */
    public function mergeCommandPaths($commandPaths)
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
        return $this->get(self::LOCAL);
    }

    /**
     * Set local mode
     *
     * @param bool $isLocal
     */
    public function setLocal($isLocal)
    {
        return $this->set(self::LOCAL, $isLocal);
    }

    /**
     * Determine whether Drush is in "simulated" mode
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
    public function setSimulate($simulate)
    {
        return $this->set(self::SIMULATE, $simulate);
    }

    /**
     * Determine whether Drush was placed in simulated mode
     */
    public function isBackend()
    {
        return $this->get(self::BACKEND);
    }

    /**
     * Set backend mode
     *
     * @param bool $backend
     */
    public function setBackend($backend)
    {
        return $this->set(self::BACKEND, $backend);
    }

    /**
     * Get the path to the coverage file.
     */
    public function coverageFile()
    {
        return $this->get(self::COVERAGE_FILE);
    }

    /**
     * Set the coverage file path
     *
     * @param string
     */
    public function setCoverageFile($coverageFile)
    {
        return $this->set(self::COVERAGE_FILE, $coverageFile);
    }

    /**
     * Determne whether Drush is in "strict" mode or not.
     */
    public function isStrict()
    {
        return $this->get(self::STRICT);
    }

    /**
     * Set strict mode
     *
     * @param bool $strict
     */
    public function setStrict($strict)
    {
        return $this->set(self::STRICT, $strict);
    }

    /**
     * Search through the provided argv list, and return
     * just the option name of any item that is an option.
     *
     * @param array $argv e.g. ['foo', '--bar=baz', 'boz']
     * @return string[] e.g. ['bar']
     */
    protected function getOptionNameList($argv)
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
     * Create a Symfony Input object
     */
    public function createInput()
    {
        // In strict mode (the default), create an ArgvInput. When
        // strict mode is disabled, create a more forgiving input object.
        if ($this->isStrict() && !$this->isBackend()) {
            return new ArgvInput($this->args());
        }

        // If in backend mode, read additional options from stdin.
        // TODO: Maybe reading stdin options should be the responsibilty of some
        // backend manager class? Could be called from preflight and injected here.
        $input = new LessStrictArgvInput($this->args());
        $input->injectAdditionalOptions($this->readStdinOptions());

        return $input;
    }

    /**
     * Read options fron STDIN during POST requests.
     *
     * This function will read any text from the STDIN pipe,
     * and attempts to generate an associative array if valid
     * JSON was received.
     *
     * @return
     *   An associative array of options, if successfull. Otherwise an empty array.
     */
    protected function readStdinOptions()
    {
        // If we move this method to a backend manager, then testing for
        // backend mode will be the responsibility of the caller.
        if (!$this->isBackend()) {
            return [];
        }

        $fp = fopen('php://stdin', 'r');
        // Windows workaround: we cannot count on stream_get_contents to
        // return if STDIN is reading from the keyboard.  We will therefore
        // check to see if there are already characters waiting on the
        // stream (as there always should be, if this is a backend call),
        // and if there are not, then we will exit.
        // This code prevents drush from hanging forever when called with
        // --backend from the commandline; however, overall it is still
        // a futile effort, as it does not seem that backend invoke can
        // successfully write data to that this function can read,
        // so the argument list and command always come out empty. :(
        // Perhaps stream_get_contents is the problem, and we should use
        // the technique described here:
        //   http://bugs.php.net/bug.php?id=30154
        // n.b. the code in that issue passes '0' for the timeout in stream_select
        // in a loop, which is not recommended.
        // Note that the following DOES work:
        //   drush ev 'print(json_encode(array("test" => "XYZZY")));' | drush status --backend
        // So, redirecting input is okay, it is just the proc_open that is a problem.
        if (drush_is_windows()) {
            // Note that stream_select uses reference parameters, so we need variables (can't pass a constant NULL)
            $read = array($fp);
            $write = null;
            $except = null;
            // Question: might we need to wait a bit for STDIN to be ready,
            // even if the process that called us immediately writes our parameters?
            // Passing '100' for the timeout here causes us to hang indefinitely
            // when called from the shell.
            $changed_streams = stream_select($read, $write, $except, 0);
            // Return on error or no changed streams (0).
            // Oh, according to http://php.net/manual/en/function.stream-select.php,
            // stream_select will return FALSE for streams returned by proc_open.
            // That is not applicable to us, is it? Our stream is connected to a stream
            // created by proc_open, but is not a stream returned by proc_open.
            if ($changed_streams < 1) {
                return [];
            }
        }
        stream_set_blocking($fp, false);
        $string = stream_get_contents($fp);
        fclose($fp);
        if (trim($string)) {
            return json_decode($string, true);
        }
        return [];
    }
}
