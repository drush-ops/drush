<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Config;

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
    public function root()
    {
        return $this->get('root');
    }

    public function uri()
    {
        return $this->get('uri');
    }

    public function remoteUser()
    {
        return $this->get('user');
    }

    public function remoteHost()
    {
        return $this->get('host');
    }

    public function isRemote()
    {
        return $this->has('host');
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
