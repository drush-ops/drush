# site:ssh

Connect to a Drupal site's server via SSH, and optionally run a shell command.

#### Examples

- <code>drush @mysite ssh</code>. Open an interactive shell on @mysite's server.
- <code>drush @prod ssh ls /tmp</code>. Run "ls /tmp" on @prod site.
- <code>drush @prod ssh git pull</code>. Run "git pull" on the Drupal root directory on the @prod site.
- <code>drush ssh git pull</code>. Run "git pull" on the local Drupal root directory.

#### Arguments

- **args**. 

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --cd=CD**. Directory to change to. Defaults to Drupal root.
- ** --tty**. 

#### Topics

- `drush docs:aliases`

#### Aliases

- ssh
- site-ssh

