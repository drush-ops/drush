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
     * Determine whether or not the provided name is an alias name.
     *
     * @param string $aliasName
     * @return bool
     */
    static public function isAliasName($aliasName)
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
        if ($this->ambiguous && !isset($this->group)) {
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
        if ($this->ambiguous && !isset($this->group)) {
            $this->env = $this->sitename;
            $this->sitename = $this->group;
            $this->group = null;
        }
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

    public function hasGroup()
    {
        return !empty($this->group);
    }

    public function group()
    {
        return $this->group;
    }

    public function sitename()
    {
        return $this->sitename;
    }

    public function hasEnv()
    {
        return !empty($this->env());
    }

    public function env()
    {
        return $this->env();
    }

    public function isSelf()
    {
        return $this->sitename() == 'self';
    }

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

        if (count($matches) == 2) {
            return $this->processJustSitename($matches[1]);
        }

        if (count($matches) == 4) {
            return $this->processGroupSitenameAndEnv($matches[1], $matches[2], $matches[3]);
        }

        return $this->processGroupSitenameAndEnv($matches[1], $matches[2]);
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
