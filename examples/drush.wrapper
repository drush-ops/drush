#!/usr/bin/env sh
#
# DRUSH WRAPPER
#
# A wrapper script which launches the Drush that is in your project's /vendor
# directory.  Copy it to the root of your project and edit as desired.
# You may rename this script to 'drush', if doing so does not cause a conflict
# (e.g. with a folder __ROOT__/drush).
#
# Below are options which you might want to add. More info at
# `drush topic core-global-options`:
#
# --local       Only discover commandfiles/site aliases/config that are
#               inside your project dir.
# --alias-path  A list of directories where Drush will search for site
#               alias files.
# --config      A list of paths to config files
# --include     A list of directories to search for commandfiles.
#
# Note that it is recommended to use --local when using a drush
# wrapper script.
#
# See the 'drush' script in the Drush installation root (../drush) for
# an explanation of the different 'drush' scripts.
#
# IMPORTANT:  Modify the path below if your 'vendor' directory has been
# relocated to another location in your composer.json file.
# `../vendor/bin/drush.launcher --local $@` is a common variant for
# composer-managed Drupal sites.
#
cd "`dirname $0`"
../vendor/bin/drush.launcher --local "$@"
