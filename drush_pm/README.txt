// $Id$

DESCRIPTION
-----------

drush_pm.module (The Drupal Shell Packet Manager) allows you to install and update contributed modules from the command line.

It provides three commands, "pm install", "pm update", and "pm info".

Run "drush help pm install" and "drush help pm update" to see supported command line options and arguments.

If you use SVN for version control and want to suppress backup of modules when performing a `pm update`, enable drush_pm_svn.module.

REQUIREMENTS
------------
No other special requirements on unix-like systems.
drush_pm uses  wget (or curl), tar and gzip, so if you're trying to use drush_pm on Windows, you have to install
these binaries before, for example from GnuWin32 (http://gnuwin32.sourceforge.net/).

------------
Written by Franz Heinzmann (frando) <http://unbiskant.org>.
No warranties of any kind. Use with care.