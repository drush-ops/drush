<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;

/**
 * An alias record is a configuration record containing well-known items.
 *
 * NOTE: AliasRecord is implemented as a Config subclass; however, it
 * should not be used as a config. (A better implementaton would be
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
     */
    public function getConfig(ConfigInterface $config, $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->get($key, $default);
        }
        return $config->get($key, $default);
    }

    public function name()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function hasRoot()
    {
        return $this->has('root');
    }

    public function root()
    {
        return $this->get('root');
    }

    public function uri()
    {
        return $this->get('uri');
    }

    public function setUri($uri)
    {
        return $this->set('uri', $uri);
    }

    public function remoteHostWithUser()
    {
        $result = $this->remoteHost();
        if (!empty($result) && $this->hasRemoteUser()) {
            $result = $this->remoteUser() . '@' . $result;
        }
        return $result;
    }

    public function remoteUser()
    {
        return $this->get('user');
    }

    public function hasRemoteUser()
    {
        return $this->has('user');
    }

    public function remoteHost()
    {
        return $this->get('host');
    }

    public function isRemote()
    {
        return !$this->isLocal();
    }

    public function isLocal()
    {
        if ($host = $this->remoteHost()) {
            return $host == 'localhost' || $host == '127.0.0.1';
        }
        return true;
    }

    public function isNone()
    {
        return empty($this->root());
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

    public function exportConfig()
    {
        $data = $this->export();

        foreach ($this->remapOptions() as $from => $to) {
            if (isset($data[$from])) {
                $data['options'][$to] = $data[$from];
                unset($data[$from]);
            }
        }

        return new Config($data);
    }

    public function legacyRecord()
    {
        return $this->exportConfig()->get('options', []);
    }

    protected function remapOptions()
    {
        return [
            'user' => 'remote-user',
            'host' => 'remote-host',
            'root' => 'root',
            'uri' => 'uri',
        ];
    }
}
