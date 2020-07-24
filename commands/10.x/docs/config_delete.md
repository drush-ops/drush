# config:delete

Delete a configuration key, or a whole object.

#### Examples

- <code>drush config:delete system.site</code>. Delete the the system.site config object.
- <code>drush config:delete system.site page.front</code>. Delete the 'page.front' key from the system.site object.

#### Arguments

- **config_name**. The config object name, for example "system.site".
- **key**. A config key to clear, for example "page.front".

#### Aliases

- cdel
- config-delete

