<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Config;

/**
 * An alias record is a configuration record containing well-known items.
 *
 * Example remote alias:
 *
 * ---
 * remote-host: www.myisp.org
 * remote-user: www-data
 * root: /path/to/drupal
 * uri: mysite.org
 *
 * Example local alias with command-specific options:
 *
 * ---
 * root: /path/to/drupal
 * uri: mysite.org
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
        return $this->get('remote-user');
    }

    public function remoteHost()
    {
        return $this->get('remote-host');
    }

    public function isRemote()
    {
        return $this->has('remote-host');
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
}
