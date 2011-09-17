# Examples bash aliases to improve your drush experience with bash.
# Copy this file to your home directory, customize it to suit, and
# source it from your ~/.bash_profile file.
#
# Features:
#
# Finds and sources drush.complete.sh from your drush directory,
# enabling autocompletion for drush commands.
#
# Creates aliases to common drush commands:
#
#	sa		- drush site-alias
#	st		- drush core-status
#
# Enhances several common shell commands to work better with drush:
#
#	dd @dev		- print the path to the root directory of @dev
#       cd @dev         - change the current working directory to @dev
#	ls @dev		- ls root folder of @dev
#	ls %files	- ls "files" directory of current site
#	ls @dev:%devel  - ls devel module directory in @dev
#       ssh @live       - ssh to the remote server @live points at
#	@dev st		- drush @dev core-status
#
# When no drush site alias arguments are provided, the standard
# shell command behaves 
#

# Find the drush executable
d=`which drush`
if [ -n $d ] ; then
  # If the file found is a symlink, resolve to the actual file
  d=`readlink -f $d`
  # Get the directory that drush is stored in
  d="${d%/*}"
  # If we have found drush.complete.sh, then source it
  if [ -f $d/drush.complete.sh ] ; then
    . $d/drush.complete.sh
  fi
fi

# Aliases for common drush commands
alias sa='drush site-alias'
alias st='drush core-status'

# Create an alias for every drush site alias.  This allows
# for commands such as `@live pml` to run `drush @live pm-list`
for a in `drush sa` ; do
  alias $a="drush $a"
  # Register another completion function for every alias to drush
  complete -o nospace -F _drush_completion $a
done


# We override the cd command to allow convenient
# shorthand notations, such as:
#   cd @site1
#   cd %modules
#   cd %devel
#   cd @site2:%files
function cd() {
  d="$1"
  if [ -n "$d" ] && [ ${d:0:1} == "@" ] || [ ${d:0:1} == "%" ]
  then
    cdd "$@";
  else
    builtin cd "$d";
  fi
}

# Do a special drush cd, handling
# shorthand notation directory names -- anything
# understood by drupal-directory
function cdd() {
  DEST=`drush drupal-directory $1 --local 2>/dev/null`
  if [ $? == 0 ]
  then
    echo "cd $DEST";
    builtin cd "$DEST";
  else
    builtin cd "$1"
  fi
}

function ls() {
  p=()
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      p[${#p[@]}]="`drush drupal-directory $a --local 2>/dev/null`"
    elif [ -n "$a" ]
    then
      p[${#p[@]}]="$a"
    fi
  done
  `which ls` "${p[@]}"
}

function cp() {
  p=()
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      p[${#p[@]}]="`drush drupal-directory $a --local 2>/dev/null`"
    elif [ -n "$a" ]
    then
      p[${#p[@]}]="$a"
    fi
  done
  `which cp` "${p[@]}"
}

function dd() {
  p=()
  drushdd=false
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      drushdd=true
    fi
    p[${#p[@]}]="$a"
  done
  if $drushdd ; then
    drush dd "${p[@]}"
  else
    `which dd` "${p[@]}"
  fi
}

function ssh() {
  $d="$1"
  if [ ${d:0:1} == "@" ]
  then
    echo drush ssh "$@"
    drush ssh "$@"
  else
    echo `which ssh` "$@"
    `which ssh` "$@"
  fi 
}
