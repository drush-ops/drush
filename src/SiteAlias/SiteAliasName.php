<?php
namespace Drush\SiteAlias;

/**
 * Parse a string that contains a site alias name, and provide convenience
 * methods to access the parts.
 *
 * When provided by users, aliases must be in one of the following forms:
 *
 *   - @sitename.env: List only sitename and environment.
 *
 *   - @env: Look up a named environment in instances where the site root
 *       is known (e.g. via cwd). In this form, there is an implicit sitename
 *       'self' which is replaced by the actual site alias name once known.
 *
 *   - @sitename: Provides only the sitename; uses the 'default' environment,
 *       or 'dev' if there is no 'default' (or whatever is there if there is
 *       only one). With this form, the site alias name has no environment
 *       until the appropriate default environment is looked up. This form
 *       is checked only after `@env` returns no matches.
 *
 * There are also two special aliases that are recognized:
 *
 *   - @self: The current bootstrapped site.
 *
 *   - @none: No alias ('root' and 'uri' unset).
 *
 * The special alias forms have no environment component.
 *
 * When provided to an API, the '@' is optional.
 *
 * Note that @sitename and @env are ambiguous. Aliases in this form
 * (that are not one of the special aliases) will first be assumed
 * to be @env, and may be converted to @sitename later.
 *
 * Note that:
 *
 * - 'sitename' and 'env' MUST NOT contain a '.' (unlike previous
 *     versions of Drush).
 * - Users SHOULD NOT create any environments that have the same name
 *     as any site name (and visa-versa).
 * - All environments in one site record SHOULD be different versions
 *     of the same site (e.g. dev / test / live).
 */
class SiteAliasName
{
    protected $sitename;
    protected $env;

    /**
     * Match the parts of a regex name.
     */
    const ALIAS_NAME_REGEX = '%^@?([a-zA-Z0-9_-]+)(\.[a-zA-Z0-9_-]+)?$%';

    /**
     * Create a new site alias name
     *
     * @param string $item
     * @return SiteAliasName
     */
    public static function parse($item)
    {
        $aliasName = new self();
        $aliasName->doParse($item);
        return $aliasName;
    }

    /**
     * Creae a SiteAliasName object from an alias name string.
     *
     * @param string $sitename The alias name for the site.
     * @param string $env The name for the site's environment.
     */
    public function __construct($sitename = null, $env = null)
    {
        $this->sitename = $sitename;
        $this->env = $env;
    }

    /**
     * Convert an alias name back to a string.
     *
     * @return string
     */
    public function __toString()
    {
        $parts = [ $this->sitename() ];
        if ($this->hasEnv()) {
            $parts[] = $this->env();
        }
        return '@' . implode('.', $parts);
    }

    /**
     * Determine whether or not the provided name is an alias name.
     *
     * @param string $aliasName
     * @return bool
     */
    public static function isAliasName($aliasName)
    {
        // Alias names provided by users must begin with '@'
        if (empty($aliasName) || ($aliasName[0] != '@')) {
            return false;
        }
        return preg_match(self::ALIAS_NAME_REGEX, $aliasName);
    }

    /**
     * Return the sitename portion of the alias name. By definition,
     * every alias must have a sitename. If the site name is implicit,
     * then 'self' is assumed.
     *
     * @return string
     */
    public function sitename()
    {
        return empty($this->sitename) ? 'self' : $this->sitename;
    }

    /**
     * Set the sitename portion of the alias name
     *
     * @param string $sitename
     */
    public function setSitename($sitename)
    {
        $this->sitename = $sitename;
    }

    /**
     * In general, all aliases have a sitename. The time when one will not
     * is when an environment name `@env` is used as a shortcut for `@self.env`
     *
     * @return bool
     */
    public function hasSitename()
    {
        return !empty($this->sitename);
    }

    /**
     * Return true if this alias name contains an 'env' portion.
     *
     * @return bool
     */
    public function hasEnv()
    {
        return !empty($this->env);
    }

    /**
     * Set the environment portion of the alias record.
     *
     * @param string
     */
    public function setEnv($env)
    {
        $this->env = $env;
    }

    /**
     * Return the 'env' portion of the alias record.
     *
     * @return string
     */
    public function env()
    {
        return $this->env;
    }

    /**
     * Return true if this alias name is the 'self' alias.
     *
     * @return bool
     */
    public function isSelf()
    {
        return ($this->sitename == 'self') && !isset($this->env);
    }

    /**
     * Return true if this alias name is the 'none' alias.
     */
    public function isNone()
    {
        return ($this->sitename == 'none') && !isset($this->env);
    }

    /**
     * Convert the parts of an alias name to its various component parts.
     *
     * @param string $aliasName a string representation of an alias name.
     */
    protected function doParse($aliasName)
    {
        // Example contents of $matches:
        //
        // - a.b:
        //     [
        //       0 => 'a.b',
        //       1 => 'a',
        //       2 => '.b',
        //     ]
        //
        // - a:
        //     [
        //       0 => 'a',
        //       1 => 'a',
        //     ]
        if (!preg_match(self::ALIAS_NAME_REGEX, $aliasName, $matches)) {
            return false;
        }

        // If $matches contains only two items, then assume the alias name
        // contains only the environment.
        if (count($matches) == 2) {
            return $this->processSingleItem($matches[1]);
        }

        $this->sitename = $matches[1];
        $this->env = ltrim($matches[2], '.');
        return true;
    }

    /**
     * Process an alias name provided as '@sitename'.
     *
     * @param string $sitename
     * @return true
     */
    protected function processSingleItem($item)
    {
        if ($this->isSpecialAliasName($item)) {
            $this->setSitename($item);
            return true;
        }
        $this->sitename = '';
        $this->env = $item;
        return true;
    }

    /**
     * Determine whether the requested name is a special alias name.
     *
     * @param string $item
     * @return boolean
     */
    protected function isSpecialAliasName($item)
    {
        return ($item == 'self') || ($item == 'none');
    }
}
