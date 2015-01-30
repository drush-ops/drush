Strict Option Handling
======================

Some Drush commands use strict option handling; these commands require that all Drush global option appear on the command line before the Drush command name.

One example of this is the core-rsync command:

      drush --simulate core-rsync -v @site1 @site2

The --simulate option is a Drush global option that causes Drush to print out what it would do if the command is executed, without actually taking any action. Commands such as core-rsync that use strict option handling require that --simulate, if used, must appear before the command name. Most Drush commands allow the --simulate to be placed anywhere, such as at the end of the command line.

The -v option above is an rsync option. In this usage, it will cause the rsync command to run in verbose mode. It will not cause Drush to run in verbose mode, though, because it appears after the core-rsync command name. Most Drush commands would be run in verbose mode if a -v option appeared in the same location.

The advantage of strict option handling is that it allows Drush to pass options and arguments through to a shell command. Some shell commands, such as rsync and ssh, either have options that cannot be represented in Drush. For example, rsync allows the --exclude option to appear multiple times on the command line, but Drush only allows one instance of an option at a time for most Drush commands. Strict option handling overcomes this limitation, plus possible conflict between Drush options and shell command options with the same name, at the cost of greater restriction on where global options can be placed.

