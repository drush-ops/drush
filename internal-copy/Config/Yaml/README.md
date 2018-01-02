Yaml Component
==============

The Yaml component loads and dumps YAML files.

This is a COPY of symfony/yaml re-namespaced to Drush\Config\Yaml. This is
here so that Drush can parse its yaml config and aliasfiles prior to autoloading
any Symfony packages. This helps avoid dependency conflicts when the global
Drush includes the autoload file from Drupal.

DO NOT USE THESE CLASSES OUTSIDE OF PREFLIGHT. Instead, use symfony/yaml.

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/yaml/index.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
