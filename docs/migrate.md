Defining and running migrations
===============================

The Migrate API delivers services for migrating data from a source system to Drupal. This API is provided by the core `migrate` module. In order to migrate data to Drupal, you'll need to create migrations for each type of destination data.

These commands are an alternative to https://www.drupal.org/project/migrate_tools. Don't use that module if you use these commands.

Defining migrations
-------------------

Learn how to create migrations from the Drupal official documentation:

* Migrate API overview: https://www.drupal.org/docs/8/api/migrate-api/migrate-api-overview
* Drupal API: https://api.drupal.org/api/drupal/core%21modules%21migrate%21migrate.api.php/group/migration

Running migrations
------------------

Drush provides a set of commands that allows to run migration operations such as importing, [checking the current status of migrations](commands/migrate_status.md), [rolling-back migrations](commands/migrate_rollback.md
), [stopping an ongoing migration](commands/migrate_stop.md), etc. Such commands are available *only* when the `migrate` module is enabled. In order the get a full list of migrate commands, type:

    drush --filter=migrate

To get help on each command run drush with the command name as parameter and the `--help` option. For example next command will show details about the [migrate:import](commands/migrate_import.md) Drush command:

    drush migrate:import --help
