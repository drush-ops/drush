Site Alias Manager
==================

The [Site Alias Manager (SAM)](https://github.com/drush-ops/drush/blob/master/src/SiteAlias/SiteAliasManager.php) service is used to retrieve information about one or all of the site aliases for the current installation. 

- An informative example is the [browse command](https://github.com/drush-ops/drush/blob/master/src/Commands/core/BrowseCommands.php)
- A commandfile gets access to the SAM by implementing the SiteAliasManagerAwareInterface and *use*ing the SiteAliasManagerAwareTrait trait. Then you gain access via `$this->siteAliasManager()`.
- If an alias was used for the current request, it is available via $this->siteAliasManager()->getself().
- The SAM generally deals in [AliasRecord](https://github.com/drush-ops/drush/blob/master/src/SiteAlias/AliasRecord.php) objects. That is how any given site alias is represented. See its methods for determining things like whether the alias points to a local host or remote host.
- [An example site alias file](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.aliases.yml).
- [Dynamically alter site aliases](https://raw.githubusercontent.com/drush-ops/drush/master/examples/Commands/SiteAliasAlterCommands.php). 
