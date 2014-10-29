# BASH completion script for Drush.
#
# Place this in your /etc/bash_completion.d/ directory or source it from your
# ~/.bash_completion or ~/.bash_profile files.  Alternatively, source
# examples/example.bashrc instead, as it will automatically find and source
# this file.

# Ensure drush is available.
which drush > /dev/null || alias drush &> /dev/null || return

__drush_ps1() {
  f="${TMPDIR:-/tmp/}/drush-env/drush-drupal-site-$$"
  if [ -f $f ]
  then
    __DRUPAL_SITE=$(cat "$f")
  else
    __DRUPAL_SITE="$DRUPAL_SITE"
  fi

  [[ -n "$__DRUPAL_SITE" ]] && printf "${1:- (%s)}" "$__DRUPAL_SITE"
}

# Completion function, uses the "drush complete" command to retrieve
# completions for a specific command line COMP_WORDS.
_drush_completion() {
  # Set IFS to newline (locally), since we only use newline separators, and
  # need to retain spaces (or not) after completions.
  local IFS=$'\n'
  # The '< /dev/null' is a work around for a bug in php libedit stdin handling.
  # Note that libedit in place of libreadline in some distributions. See:
  # https://bugs.launchpad.net/ubuntu/+source/php5/+bug/322214
  COMPREPLY=( $(drush --early=includes/complete.inc "${COMP_WORDS[@]}" < /dev/null 2> /dev/null) )
}

# Register our completion function. By default, Drush only installs
# one executable, "drush", so we only register completions by that
# name.
#
# If you'd like to register completions for aliases, add the alias
# names to the end of the command below. For example, in
# examples/example.bashrc we create an alias dr='drush'. To enable
# bash completion for the "dr" alias, one would change the command to,
#
#   complete -o bashdefault ... -F _drush_completion drush dr
#
# Other aliases can be appended in the same manner.
#
complete -o bashdefault -o default -o nospace -F _drush_completion drush
