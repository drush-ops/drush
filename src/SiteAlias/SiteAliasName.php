<?php
namespace Drush\SiteAlias;

/**
 * Parse a string that contains an alias name, and provide convenience
 * methods to access the parts.
 *
 * When provided by users, aliases must be in one of the following forms:
 *
 *   - @group.sitename.env: Fully specify group, sitename and envioronment.
 *
 *   - @sitename.env: List only sitename and environment.
 *
 *   - @sitename: Provides only the sitename; uses the 'default' environment,
 *       or 'dev' if there is no 'default' (or whatever is there if there is
 *       only one).
 *
 *   - @group.sitename: Same environment lookup as above for a site specified
 *       in a group.
 *
 * When provided to an API, the '@' is optional.
 *
 * Note that @sitename.env and @group.sitename are ambiguous. Drush will
 * attempt to interpret single-dot aliases both ways; however, having a site
 * with the same name as a group will prevent the use of the @group.sitename
 * alias form for that group.
 *
 * Note that:
 *
 * - 'group', 'sitename' and 'env' MUST NOT contain a '.' (unlike previous
 *     versions of Drush).
 * - Users SHOULD NOT create a group that has the same name as any single site.
 * - Users SHOULD NOT use the same 'sitename' in multiple groups. If they do,
 *     then they SHOULD always use @group.sitename.env whenever referencing
 *     one of the sitenames. In instances where they do not, Drush will return
 *     an arbitrary matching site.
 */
class SiteAliasName
{
    protected $group;
    protected $sitename;
    protected $env;
    protected $ambiguous;

    /**
     * Match the parts of a regex name after the '@'.
     */
    const ALIAS_NAME_REGEX = '%^@?([a-zA-Z0-9_-]+)(\.[a-zA-Z0-9_-]+)?(\.[a-zA-Z0-9_-]+)?$%';

    /**
     * Creae a SiteAliasName object from an alias name string.
     *
     * @param string $aliasName a string representation of an alias name.
     */
    public function __construct($aliasName)
    {
        $this->parse($aliasName);
    }

    /**
     * Convert an alias name back to a string.
     *
     * @return string
     */
    public function __toString()
    {
        $parts = [ $this->sitename() ];
        if ($this->hasGroup()) {
            array_unshift($parts, $this->group());
        }
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
     * If the alias name was ambiguous, assume for now that it was
     * in the form '@group.sitename'.
     */
    public function assumeAmbiguousIsGroup()
    {
        if ($this->ambiguous && !$this->hasGroup()) {
            $this->group = $this->sitename;
            $this->sitename = $this->env;
            $this->env = null;
        }
    }

    /**
     * If the alias name was ambiguous, assume for now that it was
     * in the form '@sitename.env'.
     */
    public function assumeAmbiguousIsSitename()
    {
        if ($this->ambiguous && !$this->hasEnv()) {
            $this->env = $this->sitename;
            $this->sitename = $this->group;
            $this->group = null;
        }
    }

    /**
     * In the case of calling SiteAliasManager::getMultiple(),
     * we are interested in alias names that could be:
     *
     *   - @group
     *   - @group.sitename
     *   - @sitename
     *
     * This method will return the first component of an alias
     * in one of those forms (the group or sitename), or 'false'
     * for any alias name that does not match that pattern.
     *
     * @return string|false
     */
    public function couldBeCollectionName()
    {
        $this->assumeAmbiguousIsSitename();
        if ($this->hasGroup()) {
            return false;
        }
        return $this->sitename();
    }

    /**
     * If this alias name is being used to call SiteALiasManager::getMultipl(),
     * and it is in the form @group.sitename, then this method will return
     * the 'sitename' component. Otherwise it returns 'false'.
     *
     * @return string|false
     */
    public function sitenameOfGroupCollection()
    {
        if (!$this->couldBeCollectionName() || !$this->hasEnv()) {
            return false;
        }
        return $this->env();
    }

    /**
     * Returns true if alias name was provided in an ambiguous form.
     *
     * @return bool
     */
    public function isAmbiguous()
    {
        return $this->ambiguous;
    }

    /**
     * Clears up the ambiguity of an alias name object once it is found
     * as either a '@sitename.env' or a '@group.sitename'.
     */
    public function disambiguate()
    {
        $this->ambiguous = false;
    }

    /**
     * Return true if this alias name has a group.
     *
     * @return bool
     */
    public function hasGroup()
    {
        return !empty($this->group);
    }

    /**
     * Return the name of the group portion of the alias name.
     *
     * @return string
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * Set the name of the group portion of the alias name.
     *
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Return the sitename portion of the alias name. By definition,
     * every alias must have a sitename. If the alias is in the form
     * @a.b.c, then the sitename will always be 'b'. If the alias is
     * in the form @e.f, then the sitename might be e, (if assumeAmbiguousIsGroup()
     * was called most recently) or it might be f (if assumeAmbiguousIsSitename()
     * was called more recently).
     *
     * @return string
     */
    public function sitename()
    {
        return $this->sitename;
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
        return $this->sitename() == 'self';
    }

    /**
     * Return true if this alias name is the 'none' alias.
     */
    public function isNone()
    {
        return $this->sitename() == 'none';
    }

    /**
     * Convert the parts of an alias name to its various component parts.
     *
     * @param string $aliasName a string representation of an alias name.
     */
    protected function parse($aliasName)
    {
        // Example contents of $matches:
        //
        // - a.b.c:
        //     [
        //       0 => 'a.b.c',
        //       1 => 'a',
        //       2 => '.b',
        //       3 => '.c',
        //     ]
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

        // If $matches contains only two items, then the alias name contains
        // only the sitename.
        if (count($matches) == 2) {
            return $this->processJustSitename($matches[1]);
        }

        // If there are four items in $matches, then the group, sitename
        // and env were all specified.
        if (count($matches) == 4) {
            $group = $matches[1];
            $sitename = ltrim($matches[2], '.');
            $env = ltrim($matches[3], '.');
            return $this->processGroupSitenameAndEnv($group, $sitename, $env);
        }

        // Otherwise, it is ambiguous: the alias name might be @group.sitename,
        // or it might be @sitename.env.
        return $this->processAmbiguous($matches[1], ltrim($matches[2], '.'));
    }

    /**
     * Process an alias name provided as '@sitename'.
     *
     * @param string $sitename
     * @return true
     */
    protected function processJustSitename($sitename)
    {
        $this->group = '';
        $this->sitename = $sitename;
        $this->env = '';
        $this->ambiguous = false;
        return true;
    }

    /**
     * Process an alias name provided as '@group.sitename.env'.
     *
     * @param string $group
     * @param string $sitename
     * @param string $env
     * @return true
     */
    protected function processGroupSitenameAndEnv($group, $sitename, $env)
    {
        $this->group = $group;
        $this->sitename = $sitename;
        $this->env = $env;
        $this->ambiguous = false;
        return true;
    }

    /**
     * Process a two-part alias name that could be either '@group.sitename'
     * or '@sitename.env'. We will start out assuming that the form was
     * '@sitename.env'. The caller may use assumeAmbiguousIsGroup() or
     * assumeAmbiguousIsSitename() to switch its 'mode'.
     *
     * @param string $groupOrSitename
     * @param string $sitenameOrEnv
     * @return true
     */
    protected function processAmbiguous($groupOrSitename, $sitenameOrEnv)
    {
        $this->group = '';
        $this->sitename = $groupOrSitename;
        $this->env = $sitenameOrEnv;
        $this->ambiguous = true;
        return true;
    }
}
