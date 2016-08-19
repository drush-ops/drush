Install a global Drush via Composer
------------------
To install Drush globally for a single user follow the instructions below, or [watch a video by Drupalize.me](https://youtu.be/eAtDaD8xz0Q).

1. [Install Composer globally][].
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

* [Documentation for composer's require command](http://getcomposer.org/doc/03-cli.md#require).
* Uninstall with : `composer global remove drush/drush`

Install Drush for all users via Composer
------------
If you need Drush installed for all users on a system using Composer, [install Composer globally][] then follow the steps below.

**Important:** Run these shell commands as a privileged user with write access to `/opt` and `/usr/local/bin` or prefix with `sudo`.

```sh
# Create and/or navigate to a path for the single Composer Drush install.
mkdir --parents /opt/drush-8.x
cd /opt/drush-8.x
# Initialise a new Composer project that requires Drush.
composer init --require=drush/drush:8.* -n
# Configure the path Composer should use for the Drush vendor binaries.
composer config bin-dir /usr/local/bin
# Install Drush. 
composer install
```

### Getting Updates

Use composer to update Drush just as you would with any other composer managed project. The [vendor binaries][] will be updated for all users.

```sh
# Navigate to the Drush install path.
cd /opt/drush-8.x
# Run composer update
composer update
```

### Major Version Upgrade

If upgrading to a new major version the steps are the same, with one addition:

```sh
# Remove the existing symlinks to Drush vendor binaries. 
find /usr/local/bin -lname '/opt/drush*' -exec unlink \{\} \;
# Follow the steps shown above, starting with creation of a new path.
mkdir --parents /opt/drush-9.x
cd /opt/drush-9.x
```

**Important:** At the time of writing composer will warn you if it cannot create [vendor binaries][] due to a name conflict with an existing file. 

[Install Composer globally]: https://getcomposer.org/doc/00-intro.md#globally
[vendor binaries]: https://getcomposer.org/doc/articles/vendor-binaries.md

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
- When creating site aliases for Windows remote machines, pay particular attention to information presented in the `example.aliases.drushrc.php` file, especially when setting values for `'remote-host'` and `'os'`, as these are very important when running `drush rsync` and `drush sql-sync` commands.
