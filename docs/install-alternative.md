Install a global Drush via Composer
------------------
Follow the instructions below, or [watch a video by Drupalize.me](https://youtu.be/eAtDaD8xz0Q).

1. [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).
1. Add composer's `bin` directory to the system path by placing `export PATH="$HOME/.composer/vendor/bin:$PATH"` into your ~/.bash_profile (Mac OS users) or into your ~/.bashrc (Linux users).		
1. Install latest stable Drush: `composer global require drush/drush`.
1. Verify that Drush works: `drush status`

#### Notes
* Update to latest release (per your specification in ~/.composer/composer.json): `composer global update`
* Install a specific version of Drush:

        # Install a specific version of Drush, e.g. Drush 7.1.0
        composer global require drush/drush:7.1.0

        # Install master branch as a git clone. Great for contributing back to Drush project.
        composer global require drush/drush:dev-master --prefer-source

* Alternate way to install for all users via Composer:

        COMPOSER_HOME=/opt/drush COMPOSER_BIN_DIR=/usr/local/bin COMPOSER_VENDOR_DIR=/opt/drush/7 composer require drush/drush:7

* [Documentation for composer's require command.](http://getcomposer.org/doc/03-cli.md#require)
* Uninstall with : `composer global remove drush/drush`

Windows
------------
Drush on Windows is not recommended, since Drush's test suite is not running there ([help wanted](https://github.com/drush-ops/drush/issues/1612)).

* [Acquia Dev Desktop](https://www.acquia.com/downloads) is excellent, and includes Drush. See the terminal icon after setting up a web site.
* Or consider running Linux/OSX via Virtualbox. [Drupal VM](http://www.drupalvm.com/) and [Vlad](https://github.com/hashbangcode/vlad) are popular.* These Windows packages include Drush and its dependencies (including MSys).     * [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).    * [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).    * [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).
* Or install LAMP on your own, and run Drush via [Git's shell](https://git-for-windows.github.io/), in order to insure that [all depedencies](https://github.com/acquia/DevDesktopCommon/tree/master/bintools-win/msys/bin) are available.   
* Whenever the documentation or the help text refers to `drush [option] <command>` or something similar, 'drush' may need to be replaced by 'drush.bat'.
* When creating site aliases for Windows remote machines, pay particular attention to information presented in the example.aliases.drushrc.php file, especially when setting values for 'remote-host' and 'os', as these are very important when running Drush rsync and Drush sql-sync commands.
