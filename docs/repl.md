The [php:cli command](commands/php_cli.md) is interactive PHP REPL with your bootstrapped site (remote or local). It’s a Drupal code playground. You can do quick code experimentation, grab some data, or run Drush commands. This can also help with debugging certain issues. See [this blog post](http://blog.damiankloip.net/2015/drush-php) for an introduction. Run `help` for a list of commands.

Any global [PsySH configuration](https://github.com/bobthecow/psysh/wiki/Configuration) is loaded by Drush. If you prefer a config file that is specific to the project (and can be checked in with other source code), [set the environment variable](https://github.com/bobthecow/psysh/wiki/Configuration#specifying-a-different-config-file) `PSYSH_CONFIG=</path/to/config-file>`. This file then takes precedence over any global file.

Entity classes are available without their namespace. For example, Node::load() works instead of Drupal\Node\entity\Noad::load().
