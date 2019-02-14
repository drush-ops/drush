Overview
==========================
Generators jump start your coding by building all the boring boilerplate code for you. After running a `drush generate [foo]` command, you have a guide for where to insert your custom logic.

Drush's generators reuse classes provided by the excellent [Drupal Code Generator](https://github.com/Chi-teck/drupal-code-generator) project. See its [Commands directory](https://github.com/Chi-teck/drupal-code-generator/tree/master/src/Command/Drupal_8) for inspiration.

Writing Custom Generators
==========================
Drupal modules may supply their own Generators, just like they can supply Commands.

See [Woot module](https://github.com/drush-ops/drush/blob/master/tests/functional/resources/modules/d8/woot), which Drush uses for testing. Specifically,

  1. Write a class similar to [ExampleGenerator](https://github.com/drush-ops/drush/tree/master/tests/functional/resources/modules/d8/woot/src/Generators/). Implement your custom logic in the interact() method. Typically this class is placed in the src/Generators directory.
  1. Add a .twig file to the same directory. This template specifies what gets output from the generator.
  1. Add your class to your module's drush.services.yml file ([example](https://github.com/drush-ops/drush/blob/master/tests/functional/resources/modules/d8/woot/drush.services.yml)). Use the tag `drush.generator` instead of `drush.command`.
  1. Perform a `drush cache-rebuild` to compile your drush.services.yml changes into the Drupal container.

Global Generators
==============================

Generators that don't ship inside Drupal modules are called 'global' generators. In general, its better to use modules to carry your generators. If you still prefer using a global generator, please note:

1. The file's namespace should be `\Drush\Generators`.
1. The filename must be have a name like Generators/FooGenerator.php
    1. The prefix `Foo` can be whatever string you want. The file must end in `Generator.php`
    1. The enclosing directory must be named `Generators`
1. The directory above Generators must be one of:
    1.  A Folder listed in the 'include' option. include may be provided via config or via CLI.
    1.  ../drush, /drush or /sites/all/drush. These paths are relative to Drupal root.