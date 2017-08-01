<?php
namespace Drush\SiteAlias;

/**
 * Identify a site.
 *
 * Alias specifications come in several forms:
 *
 * - An alias
 *   - @alias
 *
 * - A site specification
 *   - /path/to/drupal#sitename
 *   - user@server/path/to/drupal#sitename
 *   - user@server/path/to/drupal            (sitename == server)
 *   - user@server#sitename                  (only if $option['r'] set in some drushrc file on server)
 *
 * - A multisite name
 *   - #sitename                             (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 *   - sitename                              (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 *
 */
class SiteAliasSpec
{
    protected $rawSpec;
    protected $root;

    public function __construct($spec, $root = '')
    {
        $this->rawSpec = $spec;
    }
}
