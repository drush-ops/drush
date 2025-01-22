Inspecting Drupal dependencies
==============================
:octicons-tag-24: 13.4+

These commands allow a developer or site builder to inspect the Drupal dependencies. It's similar with Composer's `why` command but acts in the Drupal realm, by showing dependencies between modules or config entities.

Find module dependants
----------------------

Drupal modules are able to define other modules as dependencies, using the module's [metadata info.yml file](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file). To get all modules that depend on a given module type:

    drush why:module node --type=module

This will show all the _installed_ module dependents of `node` module. The results are rendered visually as a tree, making it easy to understand the nested relations. It also marks visually the circular dependencies.

If you want to get the dependency tree as data, use the `--format` option. E.g., `--format=yaml` or `--format=json`.

The above command only rendered the dependency tree of _installed_ modules. If you need to get the module dependants regardless whether they are installed or not, use the `--no-only-installed` option/flag:

    drush why:module node --type=module --no-only-installed

Config entities are able to declare [dependencies on modules](https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-entity-dependencies). You can find also the config entities that depend on a given module. The following command shows all config entities depending on `node` module:

    drush why:module node --type=config

Dependents are also rendered as a tree, showing a nested structure. The `--format` option can be used in the same way, to get a machine-readable structure.

Find config entity dependants
-----------------------------

Config entities are able also to declare [dependencies on other config entities](https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-entity-dependencies). With `why:config` Drush command we can determine the config entities depending on a specific entity:

    drush why:config node.type.article

This will also render the results in a structured tree visualisation. Same, the `--format` option can be used to get data structured as `json`, `yaml`, etc.
