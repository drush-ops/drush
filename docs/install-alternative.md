Install a global Drush via Composer
------------------
To install Drush globally for a single user follow the instructions below, or [watch a video by Drupalize.me](https://youtu.be/eAtDaD8xz0Q). 

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

* [Documentation for composer's require command.](http://getcomposer.org/doc/03-cli.md#require)
* Uninstall with : `composer global remove drush/drush`

Install Drush for all users via Composer
------------
If you need a common Drush install available for all users on a system, follow the composer install steps below.

1. [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).
1. Create and/or navigate to a directory path for the single composer drush install. In this example we are going to install drush version 7.x

        sudo mkdir --parents /opt/drush/7.x
        cd /opt/drush/7.x

1. Initialise a new composer project that requires drush. Here you may also specify the version of drush you wish to use for all users. In this example we specify drush version 6.x

        sudo composer init --require=drush/drush:7.* -n

1. Configure the path composer should use for the drush library's executables or its 'bin' directory. Choose a directory path that is used in the `$PATH` configuration for all users, for example `/usr/local/bin`

        sudo composer config bin-dir /usr/local/bin

1. Now run the composer install command. A `composer.json` file containing each of the configurations we just did will be used to install drush at the version and location we just specified.

        sudo composer install

1. Finally, if you are using bash you can enable bash command completion for all users by simply symlinking the `drush.complete.sh` shell script into the correct location.

        sudo ln -s /usr/local/bin/drush.complete.sh \
          /etc/bash_completion.d/drush

**Important Tip:** When installing *drush 6.x* there are *dependencies not managed by composer* that will require download on first run. You
should execute drush as a privelged user once after install (run `sudo drush --version`) to allow download of those libraries.

### Updates

Use composer to update the drush library just as you would with any other composer managed project.

1. Navigate to the drush install directory path. This is the path where your `composer.json` file for the drush install was created.

        cd /opt/drush/7.x

1. Run composer update

        sudo composer update

After composer update completes, the binaries and library will be updated for all users.

### Major Version Upgrade

If upgrading to a new major version, simply create a new directory path for the new major version number and follow the same steps shown above. The binaries for drush will be overwritten with the new major version.

In this way you can even switch forward or back between major versions if required.


Windows
------------
Drush on Windows is not recommended, since Drush's test suite is not running there ([help wanted](https://github.com/drush-ops/drush/issues/1612)).

- [Acquia Dev Desktop](https://www.acquia.com/downloads) is excellent, and includes Drush. See the terminal icon after setting up a web site.
- Or consider running Linux/OSX via Virtualbox. [Drupal VM](http://www.drupalvm.com/) and [Vlad](https://github.com/hashbangcode/vlad) are popular.
- These Windows packages include Drush and its dependencies (including MSys).
    - [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).
    - [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).
    - [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).
- Or install LAMP on your own, and run Drush via [Git's shell](https://git-for-windows.github.io/), in order to insure that [all depedencies](https://github.com/acquia/DevDesktopCommon/tree/master/bintools-win/msys/bin) are available.
- Whenever the documentation or the help text refers to `drush [option] <command>` or something similar, `drush` may need to be replaced by `drush.bat`.
- When creating site aliases for Windows remote machines, pay particular attention to information presented in the `example.aliases.drushrc.php` file, especially when setting values for `'remote-host'` and `'os'`, as these are very important when running `drush rsync` and `drush sql-sync` commands.
