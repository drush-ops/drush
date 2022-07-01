!!! tip

    Drush only supports one install method. It requires that your Drupal site be built with Composer and Drush be listed as a dependency. 
    
    See the [Drush 8](http://docs.drush.org/en/8.x) or [Drush 9](https://docs.drush.org/en/9.x) docs for installing prior versions of Drush.

Install a site-local Drush and Drush Launcher.
-----------------
1. It is required that Drupal sites be built using Composer, with Drush listed as a dependency. Popular starter templates for that include [drupal-project](https://github.com/drupal-composer/drupal-project) (Drush is included) and [recommended-project](https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies) (Drush must be added). If your Composer project doesn't yet depend on Drush, run `composer require drush/drush` to add it. After this step, you may call Drush via `vendor/bin/drush`.
1. Optional. To be able to call `drush` from anywhere, install the [Drush Launcher](https://github.com/drush-ops/drush-launcher). That is a small program which listens on your $PATH and hands control to a site-local Drush that is in the /vendor directory of your Composer project.


!!! note
    - See [Usage](usage.md) for details on using Drush 
    - To use a non-default PHP, [edit ~/.bashrc so that the desired PHP is in front of your $PATH](http://stackoverflow.com/questions/4145667/how-to-override-the-path-of-php-to-use-the-mamp-path/10653443#10653443). If that is not desirable, you can change your PATH for just one request: `PATH=/path/to/php:$PATH` drush status ...`
    - To use a custom php.ini for Drush requests, [see this comment](https://github.com/drush-ops/drush/issues/3294#issuecomment-370201342). 
    - See our [guide on porting commandfiles](https://weitzman.github.io/blog/port-to-drush9) from Drush 8 to later versions. Also note that alias and config files use a new .yml format in Drush 10+.

Drupal Compatibility
-----------------
Please see: https://www.drush.org/latest/install/#drupal-compatibility

❶: EOL date for Drush 8 tbd, but estimated to be in concert with <a href="https://www.drupal.org/psa-2019-02-25">Drupal 7 EOL</a>.</td>
