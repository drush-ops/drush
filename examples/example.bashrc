# -*- mode: shell-script; mode: flyspell-prog; ispell-local-dictionary: "american" -*-
#
# Example bash aliases to improve your Drush experience with bash.
# Use `drush init` to copy this file to your home directory, rename and
# customize it to suit, and source it from your ~/.bashrc file.
#
# Example - rename this to drush.bashrc, and in your
# ~/.bashrc add:
#
#
#    if [ -f ~/.drush/drush.bashrc ] ; then
#        . ~/.drush/drush.bashrc
#    fi
#
# Features:
#
# Finds and sources drush.complete.sh from your drush directory,
# enabling autocompletion for Drush commands.
#
# Creates aliases to common Drush commands that work in a global context:
#
#       dr               - drush
#       ddd              - drush drupal-directory
#       dl               - drush pm-download
#       ev               - drush php-eval
#       sa               - drush site-alias
#       sa               - drush site-alias --local-only (show local site aliases)
#       st               - drush core-status
#       use              - drush site-set
#
# Aliases for Drush commands that work on the current drupal site:
#
#       cc               - drush cache-clear
#       cr               - drush cache-rebuild
#       cca              - drush cache-clear all
#       dis              - drush pm-disable
#       en               - drush pm-enable
#       i                - drush pm-info
#       pml              - drush pm-list
#       rf               - drush pm-refresh
#       unin             - drush pm-uninstall
#       up               - drush pm-update
#       upc              - drush pm-updatecode
#       updb             - drush updatedb
#       q                - drush sql-query
#
# Provides several common shell commands to work better with Drush:
#
#       ddd @dev         - print the path to the root directory of @dev
#       cdd @dev         - change the current working directory to @dev
#       lsd @dev         - ls root folder of @dev
#       lsd %files       - ls "files" directory of current site
#       lsd @dev:%devel  - ls devel module directory in @dev
#       @dev st          - drush @dev core-status
#       dssh @live       - ssh to the remote server @live points at
#       gitd @live pull  - run `git pull` on the drupal root of @live
#
# Drush site alias expansion is also done for the cpd command:
#
#       cpd -R @site1:%files @site2:%files
#
# Note that the 'cpd' alias only works for local sites.  Use
# `drush rsync` or gitd` to move files between remote sites.
#
# Aliases are also possible for the following standard
# commands. Uncomment their definitions below as desired.
#
#       cd                - cddl [*]
#       ls                - lsd
#       cp                - cpd
#       ssh               - dssh
#       git               - gitd
#
# These standard commands behave exactly the same as they always
# do, unless a Drush site specification such as @dev or @live:%files
# is used in one of the arguments.

# Aliases for common Drush commands that work in a global context.
alias dr='drush'
alias ddd='drush drupal-directory'
alias dl='drush pm-download'
alias ev='drush php-eval'
alias sa='drush site-alias'
alias lsa='drush site-alias --local-only'
alias st='drush core-status'
alias use='drush site-set'

# Aliases for Drush commands that work on the current drupal site
alias cc='drush cache-clear'
alias cr='drush cache-rebuild'
alias cca='drush cache-clear all'
alias dis='drush pm-disable'
alias en='drush pm-enable'
alias pmi='drush pm-info'
alias pml='drush pm-list'
alias rf='drush pm-refresh'
alias unin='drush pm-uninstall'
alias up='drush pm-update'
alias upc='drush pm-updatecode'
alias updb='drush updatedb'
alias q='drush sql-query'

# Overrides for standard shell commands. Uncomment to enable.  Alias
# cd='cdd' if you want to be able to use cd @remote to ssh to a
# remote site.

# alias cd='cddl'
# alias ls='lsd'
# alias cp='cpd'
# alias ssh='dssh'
# alias git='gitd'

# We extend the cd command to allow convenient
# shorthand notations, such as:
#   cd @site1
#   cd %modules
#   cd %devel
#   cd @site2:%files
# You must use 'cddl' instead of 'cd' if you are not using
# the optional 'cd' alias from above.
# This is the "local-only" version of the function;
# see the cdd function, below, for an expanded implementation
# that will ssh to the remote server when a remote site
# specification is used.
function cddl() {
  fastcddl "$1"
  use @self
}

# Use this function instead of 'cddl' if you have a very large number
# of alias files, and the 'cddl' function is getting too slow as a result.
# This function does not automatically set your prompt to the site that
# you 'cd' to, as 'cddl' does.
function fastcddl() {
  s="$1"
  if [ -z "$s" ]
  then
    builtin cd
  elif [ "${s:0:1}" == "@" ] || [ "${s:0:1}" == "%" ]
  then
    d="$(drush drupal-directory $1 --local-only 2>/dev/null)"
    if [ $? == 0 ]
    then
      echo "cd $d";
      builtin cd "$d";
    else
      t="$(drush site-alias $1 >/dev/null 2>/dev/null)"
      if [ $? == 0 ]
      then
        echo "Cannot cd to remote site $s"
      else
        echo "Cannot cd to $s"
      fi
    fi
  else
    builtin cd "$s";
  fi
}

# Works just like the `cddl` shell alias above, with one additional
# feature: `cdd @remote-site` works like `ssh @remote-site`,
# whereas cd above will fail unless the site alias is local.  If
# you prefer this behavior, you can add `alias cd='cdd'` to your .bashrc
function cdd() {
  s="$1"
  if [ -z "$s" ]
  then
    builtin cd
  elif [ "${s:0:1}" == "@" ] || [ "${s:0:1}" == "%" ]
  then
    d="$(drush drupal-directory $s 2>/dev/null)"
    rh="$(drush sa ${s%%:*} --fields=remote-host --format=list)"
    if [ -z "$rh" ]
    then
      echo "cd $d"
      builtin cd "$d"
    else
      if [ -n "$d" ]
      then
        c="cd \"$d\" \; bash"
        drush -s ${s%%:*} ssh --tty
        drush ${s%%:*} ssh --tty
      else
        drush ssh ${s%%:*}
      fi
    fi
  else
    builtin cd "$s"
  fi
}

# Allow `git @site gitcommand` as a shortcut for `cd @site; git gitcommand`.
# Also works on remote sites, though.
function gitd() {
  s="$1"
  if [ -n "$s" ] && [ ${s:0:1} == "@" ] || [ ${s:0:1} == "%" ]
  then
    d="$(drush drupal-directory $s 2>/dev/null)"
    rh="$(drush sa ${s%%:*} --fields=remote-host --format=list)"
    if [ -n "$rh" ]
    then
      drush ${s%%:*} ssh "cd '$d' ; git ${@:2}"
    else
      echo cd "$d" \; git "${@:2}"
      (
        cd "$d"
        "git" "${@:2}"
      )
    fi
  else
    "git" "$@"
  fi
}

# Get a directory listing on @site or @site:%files, etc, for local or remote sites.
function lsd() {
  p=()
  r=
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      p[${#p[@]}]="$(drush drupal-directory $a 2>/dev/null)"
      if [ ${a:0:1} == "@" ]
      then
        rh="$(drush sa ${a%:*} --fields=remote-host --format=list)"
        if [ -n "$rh" ]
        then
          r=${a%:*}
        fi
      fi
    elif [ -n "$a" ]
    then
      p[${#p[@]}]="$a"
    fi
  done
  if [ -n "$r" ]
  then
    drush $r ssh 'ls "${p[@]}"'
  else
    "ls" "${p[@]}"
  fi
}

# Copy files from or to @site or @site:%files, etc; local sites only.
function cpd() {
  p=()
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      p[${#p[@]}]="$(drush drupal-directory $a --local-only 2>/dev/null)"
    elif [ -n "$a" ]
    then
      p[${#p[@]}]="$a"
    fi
  done
  "cp" "${p[@]}"
}

# This alias allows `dssh @site` to work like `drush @site ssh`.
# Ssh commands, such as `dssh @site ls /tmp`, are also supported.
function dssh() {
  d="$1"
  if [ ${d:0:1} == "@" ]
  then
    drush "$d" ssh "${@:2}"
  else
    "ssh" "$@"
  fi
}

# Drush checks the current PHP version to ensure compatibility, and fails with
# an error if less than the supported minimum (currently 5.4.5). If you would
# like to try to run Drush on a lower version of PHP, you can un-comment the
# line below to skip this check. Note, however, that this is un-supported.

# DRUSH_NO_MIN_PHP=TRUE
