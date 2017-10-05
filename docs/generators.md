Overview
==========================
Generators jump start your coding by building all the boring boilerplate code for you. After running a `drush generate [foo]` command, you have a guide for where to insert your custom logic.

Drush's generators reuse classes provided by the excellent [Drupal Code Generator](https://github.com/Chi-teck/drupal-code-generator) project. See its [Commands directory](https://github.com/Chi-teck/drupal-code-generator/tree/master/src/Command/Drupal_8) for inspiration.

Writing Custom Generators
==========================
Drupal modules may supply their own Generators, just like they can supply Commands.   

See [Woot module](https://github.com/drush-ops/drush/blob/master/tests/resources/modules/d8/woot), which Drush uses for testing. Specifically,
  
  1. Write a class similar to [ExampleGenerator](https://github.com/drush-ops/drush/tree/master/tests/resources/modules/d8/woot/src/Generators/). Implement your custom logic in the interact() method. Typically this class is placed in the src/Generators directory.
  1. Add a .twig file to the same directory. This template specifies what gets output from the generator.
  1. Add your class to your module's drush.services.yml file ([example](https://github.com/drush-ops/drush/blob/master/tests/resources/modules/d8/woot/drush.services.yml)). Use the tag `drush.generator` instead of `drush.command`.
  1. Perform a `drush cache-rebuild` to compile your drush.services.yml changes into the Drupal container. 
