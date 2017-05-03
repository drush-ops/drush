!!! note

    Drush 9 (coming soon!) only supports one install method. It requires that your Drupal 8 site be built with Composer and Drush be listed as a dependency. 
    
    See the [Drush 8 docs](http://docs.drush.org/en/8.x) for installing prior versions of Drush.

Install a site-local Drush
-----------------
1. It is recommended that Drupal 8 sites be [built using Composer, with Drush listed as a dependency](https://github.com/drupal-composer/drupal-project). That project already includes Drush in its composer.json. If your Composer project doesn't yet depend on Drush, run `composer require drush/drush` to add it.
1. To run Drush, navigate to your Drupal root and call `../vendor/bin/drush`. 
1. If you want the convenience of calling `drush` instead of `../vendor/bin/drush`, copy the [examples/drush.wrapper](https://github.com/drush-ops/drush/blob/master/examples/drush.wrapper) file to your Drupal root and modify to taste.
1. If you want the old convenience of being able to call `drush` from anywhere, see [drush-shim](https://github.com/webflo/drush-shim).
1. See [Usage](http://docs.drush.org/en/master/usage/) for details on using Drush.
1. To use a non-default PHP, [edit ~/.bashrc so that the right PHP is in your $PATH](http://stackoverflow.com/questions/4145667/how-to-override-the-path-of-php-to-use-the-mamp-path/10653443#10653443).

Drupal Compatibility
-----------------
Drush Version | Drush Branch  | PHP | Compatible Drupal versions | Code Status
------------- | ---------     | --- | -------------------------- | -----------
Drush 9       | [master](https://travis-ci.org/drush-ops/drush)  | 5.6+ | D8                 | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=master">
Drush 8       | [8.x](https://travis-ci.org/drush-ops/drush)  | 5.4.5+ | D6, D7, D8                 | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=8.x">
Drush 7       | [7.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=7.x">
Drush 6       | [6.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | Unsupported
Drush 5       | [5.x](https://travis-ci.org/drush-ops/drush) | 5.2.0+ | D6, D7                     | Unsupported

