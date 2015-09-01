Pick a version
-----------------
Each version of Drush supports multiple Drupal versions.  Drush 7 is current stable Drush.

Drush Version | Branch  | PHP | Compatible Drupal versions | Code Status
------------- | ------  | --- | -------------------------- | -----------
Drush 8       | [master](https://travis-ci.org/drush-ops/drush)  | 5.4.5+ | D6, D7, D8                 | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=master">
Drush 7       | [7.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=7.x">
Drush 6       | [6.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=6.x">
Drush 5       | [5.x](https://travis-ci.org/drush-ops/drush) | 5.2.0+ | D6, D7                     | Unsupported
Drush 4       | 4.x | 5.2.0+ | D5, D6, D7                 | Unsupported
Drush 3       | 3.x | 5.2.0+ | D5, D6                     | Unsupported

Pick an install method
-----------------
The four sections below describe ways to install Drush. None is superior to the others.

Composer - One Drush for all Projects
------------------

* Optional - [video instructions by Drupalize.me.](https://youtu.be/eAtDaD8xz0Q)
* [Install Composer globally](https://getcomposer.org/doc/00-intro.md#globally).
* To install Drush 7.x (stable):

        composer global require drush/drush:7.*

* To install Drush 8.x (dev) which is required for Drupal 8:

        composer global require drush/drush:dev-master
        
* Now add Drush to your system path by placing `export PATH="$HOME/.composer/vendor/bin:$PATH"` into your ~/.bash_profile (Mac OS users) or into your ~/.bashrc (Linux users).

* To update to a newer version (what you get depends on your specification in ~/.composer/composer.json):

        composer global update
        
* Alternate commands to install some other variant of Drush:

        # Install a specific version of Drush, e.g. Drush 6.1.0
        composer global require drush/drush:6.1.0
        # Master branch as a git clone. Great for contributing back to Drush project.
        composer global require drush/drush:dev-master --prefer-source
        
* To install for all users on the server:

        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
        ln -s /usr/local/bin/composer /usr/bin/composer

        git clone https://github.com/drush-ops/drush.git /usr/local/src/drush
        cd /usr/local/src/drush
        git checkout 7.0.0-alpha5  #or whatever version you want.
        ln -s /usr/local/src/drush/drush /usr/bin/drush
        composer install
        drush --version


[Fuller explanation of the require command.](http://getcomposer.org/doc/03-cli.md#require)

**Tips:**

* If Drush cannot find an autoloaded class, run `composer self-update`. Drush often tracks composer changes closely, so you may have some problems if you are not running a recent version.
* If composer cannot find a requirement, and suggests that *The package is not available in a stable-enough version according to your minimum-stability setting*, then place the following in `$HOME/.composer/composer.json`:
```
{
  "minimum-stability": "dev"
}
```
Merge this in with any other content that may already exist in this file.

See [Configure](configure.md) for next steps.

Composer - One Drush per Project
-----------------
* If your web site is built from a composer.json file (see https://github.com/drupal-composer/drupal-project), add the following to the `require` section: `"drush/drush": "7.*"`
* Run `composer install` for a new project or `composer update` for an existing one. Do so from the same directory as composer.json.
* Optional: Copy the /examples/drush file to your project root and modify to taste. This is a handy launcher script.
* To update, change the drush/drush line and run `composer update`.

Git Clone (i.e. manual install)
-----------
1. Place the uncompressed drush.tar.gz, drush.zip, or cloned git repository in a directory that is outside of your web root.
1. Make the 'drush' command executable:

    `$ chmod u+x /path/to/drush/drush`

1. Configure your system to recognize where Drush resides. There are 3 options:
    1. Create a symbolic link to the Drush executable in a directory that is already in your PATH, e.g.:

         `$ ln -s /path/to/drush/drush /usr/bin/drush`

    1. Explicitly add the Drush executable to the PATH variable which is defined in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc that is located in your home folder, i.e.:

           `export PATH="$PATH:/path/to/drush:/usr/local/bin"`

    1. Add an alias for drush (this method can also be handy if you want to use 2 versions of Drush, for example Drush 5 or 6 (stable) for Drupal 7 development, and Drush 7 (master) for Drupal 8 development).
     To add an alias to your Drush 7 executable, add this to you shell configuration file (see list in previous option):
         `$ alias drush-master=/path/to/drush/drush`

    For options 2 and 3 above, in order to apply your changes to your current session, either log out and then log back in again, or re-load your bash configuration file, i.e.:

      `$ source .bashrc`

1. Test that Drush is found by your system:

     `$ which drush`

1. From Drush root, run Composer to fetch dependencies.

     `$ composer install`

See [Configure](configure.md) for next steps.

Windows Zip Package
----------------------------

Windows support has improved, but is still lagging. For full functionality, consider running Linux/Unix/OSX via Virtualbox, or other virtual machine platform. [The Vlad virtual machine](https://github.com/hashbangcode/vlad) is popular.

These Windows packages include Drush and its dependencies (including MSys). 

- [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).
- [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).
- [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).

Unzip the downloaded file to anywhere thats convenient on your system. 

Whenever the documentation or the help text refers to `drush [option] <command>` or something similar, 'drush' may need to be replaced by 'drush.bat'.

Most Drush commands will run in a Windows CMD shell or PowerShell, but the Git Bash shell provided by the [Git for Windows](http://msysgit.github.com) installation is the preferred shell in which to run Drush commands.

When creating site aliases for Windows remote machines, pay particular attention to information presented in the example.aliases.drushrc.php file, especially when setting values for 'remote-host' and 'os', as these are very important when running Drush rsync and Drush sql-sync commands.
