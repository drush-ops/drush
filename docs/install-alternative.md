Windows
------------
Drush on Windows is experimental, since Drush's test suite is not running there ([help wanted](https://github.com/drush-ops/drush/issues/1612)).

- [Acquia Dev Desktop](https://www.acquia.com/downloads) is excellent, and includes Drush. See the terminal icon after setting up a web site.
- Or consider running Linux/OSX via Virtualbox. [Drupal VM](http://www.drupalvm.com/) and [Vlad](https://github.com/hashbangcode/vlad) are popular.
- These Windows packages include Drush and its dependencies (including MSys).
    - [7.0.0 (stable)](https://github.com/drush-ops/drush/releases/download/7.0.0/windows-7.0.0.zip).
    - [6.6.0](https://github.com/drush-ops/drush/releases/download/6.6.0/windows-6.6.0.zip).
    - [6.0](https://github.com/drush-ops/drush/releases/download/6.0.0/Drush-6.0-2013-08-28-Installer-v1.0.21.msi).
- Or install LAMP on your own, and run Drush via [Git's shell](https://git-for-windows.github.io/), in order to insure that [all depedencies](https://github.com/acquia/DevDesktopCommon/tree/master/bintools-win/msys/bin) are available.
- When creating site aliases for Windows remote machines, pay particular attention to information presented in the `example.aliases.drushrc.php` file, especially when setting values for `'remote-host'` and `'os'`, as these are very important when running `drush rsync` and `drush sql-sync` commands.
