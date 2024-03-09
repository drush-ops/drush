# Overview

!!! tip

    Drush 11 and prior required generators to define a [drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files). This is no longer used with Drush 12+ generators. See [docs](dependency-injection.md) for injecting dependencies..

Generators jump start your coding by building all the boring boilerplate code for you. After running the [generate command](commands/generate.md), you have a guide for where to insert your custom logic.

Drush's generators reuse classes provided by the excellent [Drupal Code Generator](https://github.com/Chi-teck/drupal-code-generator) project. See its [Commands directory](https://github.com/Chi-teck/drupal-code-generator/tree/3.x/src/Command) for inspiration.

## Writing Custom Generators
Drupal modules may supply their own Generators, just like they can supply Commands.

See [Woot module](https://github.com/drush-ops/drush/tree/12.x/sut/modules/unish/woot/src/Drush/Generators), which Drush uses for testing. Specifically,

  1. Write a class similar to [ExampleGenerator](https://github.com/drush-ops/drush/tree/12.x/sut/modules/unish/woot/src/Drush/Generators). Implement your custom logic in the generate() method. Typically this class is placed under the src/Drush/Generators directory.
  2. Add a .twig file to the same directory. This template specifies what gets output from the generator.
  
## Auto-discovered Generators (PSR4)

Generators that don't ship inside Drupal modules are called *global* generators. For example see [CustomDrushGenerator](https://github.com/drush-ops/drush/blob/12.x/tests/fixtures/lib/Drush/Generators/CustomGenerator.php). In general, it is better to use modules to carry your generators. If you still prefer using a global generator, please note:

1. The generator class should be PSR4 auto-loadable.
1. The generator class namespace, relative to base namespace, should be `Drush\Generators`. For instance, if a Drush generator provider third party library maps this PSR4 autoload entry:
   ```json
   "autoload": {
     "psr-4": {
       "My\\Custom\\Library\\": "src"
     }
   }
   ```
   then the Drush global generator class namespace should be `My\Custom\Library\Drush\Generators` and the class file should be located under the `src/Drush/Generators` directory.
1. The filename must have a name like FooGenerator.php. The prefix `Foo` can be whatever string you want. The file must end in `Generator.php`.

## Site-wide Generators

Sitewide generators (as opposed to auto-discovered PSR4) have a namespace that starts with `\Drush\Generators`, the directory above Generators must be one of:
    1.  A Folder listed in the *--include* option. include may be provided via config or via CLI.
    1.  `../drush`, `/drush` or `/sites/all/drush`. These paths are relative to Drupal root.
