# Overview
Generators jump start your coding by building all the boring boilerplate code for you. After running the [generate command](commands/generate.md), you have a guide for where to insert your custom logic.

Drush's generators reuse classes provided by the excellent [Drupal Code Generator](https://github.com/Chi-teck/drupal-code-generator) project. See its [Commands directory](https://github.com/Chi-teck/drupal-code-generator/tree/10.x/src/Command) for inspiration.

## Writing Custom Generators
Drupal modules may supply their own Generators, just like they can supply Commands.

See [Woot module](https://github.com/drush-ops/drush/blob/10.x/tests/functional/resources/modules/d8/woot), which Drush uses for testing. Specifically,

  1. Write a class similar to [ExampleGenerator](https://github.com/drush-ops/drush/tree/10.x/tests/functional/resources/modules/d8/woot/src/Generators/). Implement your custom logic in the interact() method. Typically this class is placed in the src/Generators directory.
  1. Add a .twig file to the same directory. This template specifies what gets output from the generator.
  1. Add your class to your module's drush.services.yml file ([example](https://github.com/drush-ops/drush/blob/10.x/tests/functional/resources/modules/d8/woot/drush.services.yml)). Use the tag `drush.generator` instead of `drush.command`.
  1. Perform a `drush cache-rebuild` to compile your drush.services.yml changes into the Drupal container.

## Global Generators

Generators that don't ship inside Drupal modules are called *global* generators. In general, it is better to use modules to carry your generators. If you still prefer using a global generator, please note:

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
1. The global generator namespace should start with `\Drush\Generators` but this might be subject to change in the following release.
1. The filename must have a name like FooGenerator.php. The prefix `Foo` can be whatever string you want. The file must end in `Generator.php`
1. When the namespace starts with `\Drush\Generators`, the directory above Generators must be one of:
    1.  A Folder listed in the *--include* option. include may be provided via config or via CLI.
    1.  `../drush`, `/drush` or `/sites/all/drush`. These paths are relative to Drupal root.
