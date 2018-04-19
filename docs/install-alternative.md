Install a global Drush via Composer
------------------
Follow the instructions below:

1. [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).
1. Install the [cgr tool](https://github.com/consolidation/cgr) following the [instructions in that project](https://github.com/consolidation/cgr#installation-and-usage).
1. Add composer's `bin` directory to the system path by placing `export PATH="$HOME/.composer/vendor/bin:$PATH"` into your ~/.bash_profile (Mac OS users) or into your ~/.bashrc (Linux users).		
1. Install latest stable Drush: `cgr drush/drush`.
1. Verify that Drush works: `drush status`

Please do not install Drush using `composer global require`. See [Fixing the Composer Global command](https://pantheon.io/blog/fixing-composer-global-command) for more information.

#### Notes
* Update to latest release (per your specification in ~/.composer/composer.json): `cgr update drush/drush`
* Install a specific version of Drush:

        # Install a specific version of Drush, e.g. Drush 7.1.0
        cgr update drush/drush:7.1.0

        # Install 8.x branch as a git clone. Great for contributing back to Drush project.
        cgr drush/drush:8.x-dev --prefer-source


* Alternate way to install for all users via Composer:

        COMPOSER_HOME=/opt/drush COMPOSER_BIN_DIR=/usr/local/bin COMPOSER_VENDOR_DIR=/opt/drush/8 composer require drush/drush:^8

* [Documentation for composer's require command.](http://getcomposer.org/doc/03-cli.md#require)
* Uninstall with : `cgr remove drush/drush`

Windows
------------
Drush on Windows is experimental, since Drush's test suite is not running there ([help wanted](https://github.com/drush-ops/drush/issues/1612)).

* [Acquia Dev Desktop](https://www.acquia.com/downloads) is excellent, and includes Drush. See the terminal icon after setting up a web site.
* Or consider running Linux/OSX via Virtualbox. [Drupal VM](http://www.drupalvm.com/) and [Vlad](https://github.com/hashbangcode/vlad) are popular.* These Windows packages include Drush and its dependencies (including MSys).     * [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).    * [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).    * [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).
* Or install LAMP on your own, and run Drush via [Git's shell](https://git-for-windows.github.io/), in order to insure that [all depedencies](https://github.com/acquia/DevDesktopCommon/tree/8.x/bintools-win/msys/bin) are available.   
* When creating site aliases for Windows remote machines, pay particular attention to information presented in the example.aliases.drushrc.php file, especially when setting values for 'remote-host' and 'os', as these are very important when running Drush rsync and Drush sql-sync commands.
