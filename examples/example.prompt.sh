# -*- mode: shell-script; mode: flyspell-prog; ispell-local-dictionary: "american" -*-
#
# Example PS1 prompt.
#
# Use `drush init` to copy this to ~/.drush/drush.prompt.sh, and source it in ~/.bashrc
#
# Features:
#
# Displays Git repository and Drush alias status in your prompt.

__drush_ps1() {
  f="${TMPDIR:-/tmp/}/drush-env-${USER}/drush-drupal-site-$$"
  if [ -f $f ]
  then
    __DRUPAL_SITE=$(cat "$f")
  else
    __DRUPAL_SITE="$DRUPAL_SITE"
  fi

  # Set DRUSH_PS1_SHOWCOLORHINTS to a non-empty value and define a
  # __drush_ps1_colorize_alias() function for color hints in your Drush PS1
  # prompt. See example.prompt.sh for an example implementation.
  if [ -n "${__DRUPAL_SITE-}" ] && [ -n "${DRUSH_PS1_SHOWCOLORHINTS-}" ]; then
    __drush_ps1_colorize_alias
  fi

  [[ -n "$__DRUPAL_SITE" ]] && printf "${1:- (%s)}" "$__DRUPAL_SITE"
}

if [ -n "$(type -t __git_ps1)" ] && [ "$(type -t __git_ps1)" = function ] && [ "$(type -t __drush_ps1)" ] && [ "$(type -t __drush_ps1)" = function ]; then

  # This line enables color hints in your Drush prompt. Modify the below
  # __drush_ps1_colorize_alias() to customize your color theme.
  DRUSH_PS1_SHOWCOLORHINTS=true

  # Git offers various prompt customization options as well as seen in
  # https://github.com/git/git/blob/master/contrib/completion/git-prompt.sh.
  # Adjust the following lines to enable the corresponding features:
  #
  GIT_PS1_SHOWDIRTYSTATE=true
  GIT_PS1_SHOWUPSTREAM=auto
  # GIT_PS1_SHOWSTASHSTATE=true
  # GIT_PS1_SHOWUNTRACKEDFILES=true
  GIT_PS1_SHOWCOLORHINTS=true

  # The following line sets your bash prompt according to this example:
  #
  #   username@hostname ~/working-directory (git-branch)[@drush-alias] $
  #
  # See http://ss64.com/bash/syntax-prompt.html for customization options.
  export PROMPT_COMMAND='__git_ps1 "\u@\h \w" "$(__drush_ps1 "[%s]") \\\$ "'

  # PROMPT_COMMAND is used in the example above rather than PS1 because neither
  # Git nor Drush color hints are compatible with PS1. If you don't want color
  # hints, however, and prefer to use PS1, you can still do so by commenting out
  # the PROMPT_COMMAND line above and uncommenting the PS1 line below:
  #
  # export PS1='\u@\h \w$(__git_ps1 " (%s)")$(__drush_ps1 "[%s]")\$ '

  __drush_ps1_colorize_alias() {
    if [[ -n ${ZSH_VERSION-} ]]; then
      local COLOR_BLUE='%F{blue}'
      local COLOR_CYAN='%F{cyan}'
      local COLOR_GREEN='%F{green}'
      local COLOR_MAGENTA='%F{magenta}'
      local COLOR_RED='%F{red}'
      local COLOR_WHITE='%F{white}'
      local COLOR_YELLOW='%F{yellow}'
      local COLOR_NONE='%f'
    else
      # Using \[ and \] around colors is necessary to prevent issues with
      # command line editing/browsing/completion.
      local COLOR_BLUE='\[\e[94m\]'
      local COLOR_CYAN='\[\e[36m\]'
      local COLOR_GREEN='\[\e[32m\]'
      local COLOR_MAGENTA='\[\e[35m\]'
      local COLOR_RED='\[\e[91m\]'
      local COLOR_WHITE='\[\e[37m\]'
      local COLOR_YELLOW='\[\e[93m\]'
      local COLOR_NONE='\[\e[0m\]'
    fi

    # Customize your color theme below.
    case "$__DRUPAL_SITE" in
      *.live|*.prod) local ENV_COLOR="$COLOR_RED" ;;
      *.stage|*.test) local ENV_COLOR="$COLOR_YELLOW" ;;
      *.local) local ENV_COLOR="$COLOR_GREEN" ;;
      *) local ENV_COLOR="$COLOR_BLUE" ;;
    esac

    __DRUPAL_SITE="${ENV_COLOR}${__DRUPAL_SITE}${COLOR_NONE}"
  }

fi
