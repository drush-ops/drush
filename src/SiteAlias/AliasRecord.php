<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Util\ArrayUtil;

/**
 * An alias record is a configuration record containing well-known items.
 *
 * NOTE: AliasRecord is implemented as a Config subclass; however, it
 * should not be used as a config. (A better implementation would be
 * "hasa" config, but that is less convenient, as we want all of the
 * same capabilities as a config object).
 *
 * If using an alias record as config is desired, use the 'exportConfig()'
 * method.
 *
 * Example remote alias:
 *
 * ---
 * host: www.myisp.org
 * user: www-data
 * root: /path/to/drupal
 * uri: mysite.org
 *
 * Example local alias with global and command-specific options:
 *
 * ---
 * root: /path/to/drupal
 * uri: mysite.org
 * options:
 *   no-interaction: true
 * command:
 *   user:
 *     login:
 *       options:
 *         name: superuser
 */
class AliasRecord extends Config
{
    /**
     * @var string
     */
    protected $name;

    /**
     * AliasRecord constructor
     *
     * @param array|null $data Initial data for alias record
     * @param string $name Alias name or site specification for this alias record
     * @param string $env Environment for this alias record. Will be appended to
     *   the alias name, separated by a "." if provided.
     * @param string $group Group for this alias record. Will be prepended to
     *   the alias name, separated by a "." if provided. Ignored unless $name is
     *   an alias (must begin with "@").
     * @return type
     */
    public function __construct(array $data = null, $name = '', $env = '', $group = '')
    {
        parent::__construct($data);
        if (!empty($env)) {
            $name .= ".$env";
        }
        if (!empty($group)) {
            $name = preg_replace('/^@/', "@{$group}.", $name);
        }
        $this->name = $name;
    }

    /**
     * Get a value from the provided config option. Values stored in
     * this alias record will override the configuration values, if present.
     *
     * If multiple alias records need to be chained together in a more
     * complex priority arrangement, @see \Consolidation\Config\Config\ConfigOverlay.
     *
     * @param ConfigInterface $config The configuration object to pull fallback data from
     * @param string $key The data item to fetch
     * @param mixed $default The default value to return if there is no match
     *
     * @return string
     */
    public function getConfig(ConfigInterface $config, $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->get($key, $default);
        }
        return $config->get($key, $default);
    }

    /**
     * Return the name of this alias record.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Remember the name of this record
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Determine whether this alias has a root.
     */
    public function hasRoot()
    {
        return $this->has('root');
    }

    /**
     * Get the root
     */
    public function root()
    {
        return $this->get('root');
    }

    /**
     * Get the uri
     */
    public function uri()
    {
        return $this->get('uri');
    }

    /**
     * Record the uri
     *
     * @param string $uri
     */
    public function setUri($uri)
    {
        return $this->set('uri', $uri);
    }

    /**
     * Return user@host, or just host if there is no user. Returns
     * an empty string if there is no host.
     *
     * @return string
     */
    public function remoteHostWithUser()
    {
        $result = $this->remoteHost();
        if (!empty($result) && $this->hasRemoteUser()) {
            $result = $this->remoteUser() . '@' . $result;
        }
        return $result;
    }

    /**
     * Get the remote user
     */
    public function remoteUser()
    {
        return $this->get('user');
    }

    /**
     * Return true if this alias record has a remote user
     */
    public function hasRemoteUser()
    {
        return $this->has('user');
    }

    /**
     * Get the remote host
     */
    public function remoteHost()
    {
        return $this->get('host');
    }

    /**
     * Return true if this alias record has a remote host that is not
     * the local host
     */
    public function isRemote()
    {
        return !$this->isLocal();
    }

    /**
     * Return true if this alias record is for the local system
     */
    public function isLocal()
    {
        if ($host = $this->remoteHost()) {
            return $host == 'localhost' || $host == '127.0.0.1';
        }
        return true;
    }

    /**
     * Determine whether this alias does not represent any site. An
     * alias record must either be remote or have a root.
     */
    public function isNone()
    {
        return empty($this->root()) && $this->isLocal();
    }

    /**
     * Return the 'root' element of this alias if this alias record
     * is local.
     */
    public function localRoot()
    {
        if (!$this->isRemote()) {
            return $this->root();
        }

        return false;
    }

    /**
     * Export the configuration values in this alias record, and reconfigure
     * them so that the layout matches that of the global configuration object.
     */
    public function exportConfig()
    {
        return $this->remap($this->export());
    }

    /**
     * Reconfigure data exported from the form it is expected to be in
     * inside an alias record to the form it is expected to be in when
     * inside a configuration file.
     */
    protected function remap($data)
    {
        foreach ($this->remapOptionTable() as $from => $to) {
            if (isset($data[$from])) {
                unset($data[$from]);
            }
            $value = $this->get($from, null);
            if (isset($value)) {
                $data['options'][$to] = $value;
            }
        }

        return new Config($data);
    }

    /**
     * Fetch the parameter-specific options from the 'alias-parameters' section of the alias.
     * @param string $parameterName
     * @return array
     */
    protected function getParameterSpecificOptions($aliasData, $parameterName)
    {
        if (!empty($parameterName) && $this->has("alias-parameters.{$parameterName}")) {
            return $this->get("alias-parameters.{$parameterName}");
        }
        return [];
    }

    /**
     * Convert the data in this record to the layout that was used
     * in the legacy code, for backwards compatiblity.
     */
    public function legacyRecord()
    {
        $result = $this->exportConfig()->get('options', []);

        // Backend invoke needs a couple of critical items in specific locations.
        if ($this->has('paths.drush-script')) {
            $result['path-aliases']['%drush-script'] = $this->get('paths.drush-script');
        }
        if ($this->has('ssh.options')) {
            $result['ssh-options'] = $this->get('ssh.options');
        }
        return $result;
    }

    /**
     * Conversion table from old to new option names. These all implicitly
     * go in `options`, although they can come from different locations.
     */
    protected function remapOptionTable()
    {
        return [
            'user' => 'remote-user',
            'host' => 'remote-host',
            'root' => 'root',
            'uri' => 'uri',
        ];
    }
}
