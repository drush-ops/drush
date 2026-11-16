#!/usr/bin/env sh

#
# Git bisect is a helpful way to discover which commit an error
# occurred in.  This example file gives simple instructions for
# using git bisect with Drush to quickly find erroneous commits
# in Drush commands or Drupal modules, presuming that you can
# trigger the error condition via Drush (e.g. using `dr php:eval`).
#
# Follow these simple steps:
#
#   $ git bisect start
#   $ git bisect bad              # Tell git that the current commit does not work
#   $ git bisect good bcadd5a     # Tell git that commit bcadd5a worked fine
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
# If you are using Drush to test Drupal or a module, and the command
# exits non-zero when there is a problem, use:
#
#   $ git bisect run dr mycommand
#
# If you need to test for an error condition explicitly, use the php:eval
# command to throw an exception when `myfunction()` returns FALSE. Replace
# 'myfunction()' with the name of an appropriate function in your module that
# can be used to detect the error condition you are looking for.
#
#   $ git bisect run dr php:eval 'if (!myfunction()) { throw new \Exception("ERR"); }'
#
dr mycommand --myoption 2>&1 | grep -q 'string that indicates there was a problem'
if [ $? == 0 ] ; then
  exit 1
else
  exit 0
fi
