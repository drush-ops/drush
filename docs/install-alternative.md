Install a global Drush via Composer
------------------
To install Drush globally for a single user follow the instructions below, or [watch a video by Drupalize.me](https://youtu.be/eAtDaD8xz0Q).

1. [Install Composer globally][composer install global].
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
If you need Drush installed for all users on a system using composer, follow the install steps below.

### Commands Only

Just want the commands? Here you go then. Run these shell commands as a privelged user with write access to `/opt` and `/usr/local/bin` or prefix with `sudo`.

```sh
cd /opt/drush-8.x
composer init --require=drush/drush:8.* -n
composer config bin-dir /usr/local/bin
composer install
```

### Steps Explained

1. [Install Composer globally][composer install global].
1. Create and/or navigate to a directory path for the single composer Drush install. In this example we are going to install Drush version 8.x

        mkdir --parents /opt/drush-8.x
        cd /opt/drush-8.x

1. Initialise a new composer project that requires Drush. Here you may also specify the Drush [package version][composer package version] you wish composer to install. In this example we specify Drush version 8.x with the [package version][composer package version] string `8.*`

        composer init --require=drush/drush:8.* -n

1. Configure the path composer should use for the Drush package's [vendor binaries][composer vendor binaries] or command-line scripts. Choose a directory path that is used in the `$PATH` configuration for all users, for example `/usr/local/bin`:

        composer config bin-dir /usr/local/bin

1. Now run the `composer install` command. A `composer.json` file containing each of the configurations we just did has been created.

        composer install

    The `composer.json` file tells composer to install (or update) Drush at the [package version][composer package version] we specified and to put the [vendor binaries][composer vendor binaries] where all users can access them.


### Command Completion

Drush provides the `drush init` command to add command completion and shell prompt scripts into a users bash configuration. You can enable this though for all users immediately, without requiring them to run `drush init`.

Enable Drush completion for all users by symlinking the `drush.complete.sh` shell script into the correct location.

    sudo ln -s /usr/local/bin/drush.complete.sh \
      /etc/bash_completion.d/drush

### Getting Updates

Use composer to update the Drush library just as you would with any other composer managed project.

1. Navigate to the Drush install directory path. This is the path where your `composer.json` file for the Drush composer install was created.

        cd /opt/drush-8.x

1. Run composer update

        sudo composer update

After composer update completes, the binaries and library will be updated for all users.

### Major Version Upgrade

If upgrading to a new major version the steps are the same, with one addition:

1. Remove the existing symlinks to Drush [vendor binaries][composer vendor binaries]. From our example above these would be located in the `bin-dir` path `/usr/local/bin` and point to a path starting with `/opt/drush*`:

        find /usr/local/bin -lname '/opt/drush*' -exec unlink \{\} \;

1. Follow the steps shown above, starting with creation of a new directory path for the new major version number.

        mkdir --parents /opt/drush-9.x
        cd /opt/drush-9.x

**Important Tip:** At the time of writing composer will warn you if it cannot create vendor binaries due to a name conflict with an existing file. Community contributions to composer coming soon will allow you to force the overwrite of existing vendor binaries during an install or update.

[composer package version]: https://getcomposer.org/doc/articles/versions.md
[composer install global]: https://getcomposer.org/doc/00-intro.md#globally
[composer vendor binaries]: https://getcomposer.org/doc/articles/vendor-binaries.md

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
