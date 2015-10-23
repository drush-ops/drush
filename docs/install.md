Pick a version
-----------------
Drush 8 is recommended.

Drush Version | Branch  | PHP | Compatible Drupal versions | Code Status
------------- | ------  | --- | -------------------------- | -----------
Drush 8       | [master](https://travis-ci.org/drush-ops/drush)  | 5.4.5+ | D6, D7, D8                 | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=master">
Drush 7       | [7.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=7.x">
Drush 6       | [6.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=6.x">
Drush 5       | [5.x](https://travis-ci.org/drush-ops/drush) | 5.2.0+ | D6, D7                     | Unsupported

Pick an install method
-----------------
The three sections below describe ways to install Drush. If you are using Drupal 8 (or Drupal7+Composer) follow the instructions in [Composer - One Drush per Project](#composer-one-drush-per-project).

Composer - One Drush for all Projects
------------------
Follow the instructions below, or [watch a video by Drupalize.me](https://youtu.be/eAtDaD8xz0Q).

1. [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).
1. Add composer's `bin` directory to the system path by placing `export PATH="$HOME/.composer/vendor/bin:$PATH"` into your ~/.bash_profile (Mac OS users) or into your ~/.bashrc (Linux users).
1. Install latest stable Drush: `composer global require drush/drush`.
1. Verify that Drush works: `drush status`
1. See [Configure](configure.md) for next steps.

#### Notes
* Update to latest release (per your specification in ~/.composer/composer.json): `composer global update`
* Alternate commands to install a specific version of Drush:

        # Install a specific version of Drush, e.g. Drush 7.1.0
        composer global require drush/drush:7.1.0
        # Master branch as a git clone. Great for contributing back to Drush project.
        composer global require drush/drush:dev-master --prefer-source        
* Alternate way to install for all users via Composer:
        
        COMPOSER_HOME=/opt/drush COMPOSER_BIN_DIR=/usr/local/bin COMPOSER_VENDOR_DIR=/opt/drush/7 composer require drush/drush:7
* [Documentation for composer's require command.](http://getcomposer.org/doc/03-cli.md#require)

Composer - One Drush per Project
-----------------
Starting with Drupal 8, it is recommended that you [build your site using Composer, with Drush listed as a dependency](https://github.com/drupal-composer/drupal-project).   

1. Follow the instructions [Composer - One Drush for all Projects](#composer-one-drush-for-all-projects), so that you have a copy of Drush 8.x on your PATH.  When you run `drush`, it will notice that you have a site-local Drush with the site you have selected, and will use that one instead.  This gives you the convenience of running `drush` without specifying the full path to the executable, without sacrificing the safety provided by a site-local Drush.
2. Optional: Copy the examples/drush.wrapper file to your project root and modify to taste. This is a handy launcher script; add --local here to turn off all global configuration locations, and maintain absolute consistency over the configuration/aliases/commandfiles settings for your Drush calls.
3. Note that if you have multiple Drupal sites on your system, it is possible to use a different version of Drush with each one.

See [Configure](configure.md) for next steps.

Windows Zip Package
----------------------------
Windows support has improved, but is still lagging. For full functionality, consider running Linux/Unix/OSX via Virtualbox, or other virtual machine platform. [The Vlad virtual machine](https://github.com/hashbangcode/vlad) is popular.

* These Windows packages include Drush and its dependencies (including MSys). 
    * [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).
    * [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).
    * [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).
* Unzip the downloaded file to anywhere thats convenient on your system. 
* Whenever the documentation or the help text refers to `drush [option] <command>` or something similar, 'drush' may need to be replaced by 'drush.bat'.
* Most Drush commands will run in a Windows CMD shell or PowerShell, but the Git Bash shell provided by the [Git for Windows](http://msysgit.github.com) installation is the preferred shell in which to run Drush commands.
* When creating site aliases for Windows remote machines, pay particular attention to information presented in the example.aliases.drushrc.php file, especially when setting values for 'remote-host' and 'os', as these are very important when running Drush rsync and Drush sql-sync commands.

See [Configure](configure.md) for next steps.
