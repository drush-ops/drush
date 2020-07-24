# site:set

Set a site alias that will persist for the current session.

Stores the site alias being used in the current session in a temporary
file.

#### Examples

- <code>drush site:set @dev</code>. Set the current session to use the @dev alias.
- <code>drush site:set user@server/path/to/drupal#sitename</code>. Set the current session to use a remote site via site specification.
- <code>drush site:set /path/to/drupal#sitename</code>. Set the current session to use a local site via site specification.
- <code>drush site:set -</code>. Go back to the previously-set site (like `cd -`).
- <code>drush site:set</code>. Without an argument, any existing site becomes unset.

#### Arguments

- **site**. Site specification to use, or "-" for previous site. Omit this argument to unset.

#### Aliases

- use
- site-set

