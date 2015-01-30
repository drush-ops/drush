#!/usr/bin/env sh

#
# Git bisect is a helpful way to discover which commit an error
# occurred in.  This example file gives simple instructions for
# using git bisect with Drush to quickly find erroneous commits
# in Drush commands or Drupal modules, presuming that you can
# trigger the error condition via Drush (e.g. using `drush php-eval`).
#
# Follow these simple steps:
#
#   $ git bisect start
#   $ git bisect bad              # Tell git that the current commit does not work
#   $ git bisect good bcadd5a     # Tell drush that the commithash 12345 worked fine
#   $ git bisect run mytestscript.sh
#
# 'git bisect run' will continue to call 'git bisect good' and 'git bisect bad',
# based on whether the script's exit code was 0 or 1, respectively.
#
# Replace 'mytestscript.sh' in the example above with a custom script that you
# write yourself.  Use the example script at the end of this document as a
# guide.  Replace the example command with one that calls the Drush command
# that you would like to test, and replace the 'grep' string with a value
# that appears when the error exists in the commit, but does not appear when
# commit is okay.
#
# If you are using Drush to test Drupal or an external Drush module, use:
#
#   $ git bisect run drush mycommand --strict=2
#
# This presumes that there is one or more '[warning]' or '[error]'
# messages emitted when there is a problem, and no warnings or errors
# when the commit is okay.  Omit '--strict=2' to ignore warnings, and
# signal failure only when 'error' messages are emitted.
#
# If you need to test for an error condition explicitly, to find errors
# that do not return any warning or error log messages on their own, you
# can use the Drush php-eval command to force an error when `myfunction()`
# returns FALSE. Replace 'myfunction()' with the name of an appropriate
# function in your module that can be used to detect the error condition
# you are looking for.
#
#   $ git bisect run drush ev 'if(!myfunction()) { return drush_set_error("ERR"); }'
#
drush mycommand --myoption 2>&1 | grep -q 'string that indicates there was a problem'
if [ $? == 0 ] ; then
  exit 1
else
  exit 0
fi
