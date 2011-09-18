# Example bash aliases to improve your drush experience with bash.
# Copy this file to your home directory, rename and customize it to 
# suit, and source it from your ~/.bash_profile file.
#
# Example - place this in your ~/.bash_profile:
#
#    if [ -f $HOME/.drush_bashrc ] ; then
#        . $HOME/.drush_bashrc
#    fi
#
# Features:
#
# Finds and sources drush.complete.sh from your drush directory,
# enabling autocompletion for drush commands.
#
# Creates aliases to common drush commands:
#
#       dr              - drush
#       sa              - drush site-alias
#       st              - drush core-status
#
# Enhances several common shell commands to work better with drush:
#
#       dd @dev         - print the path to the root directory of @dev
#       cd @dev         - change the current working directory to @dev
#       ls @dev         - ls root folder of @dev
#       ls %files       - ls "files" directory of current site
#       ls @dev:%devel  - ls devel module directory in @dev
#       @dev st         - drush @dev core-status
#       ssh @live       - ssh to the remote server @live points at
#       git @live pull  - run `git pull` on the drupal root of @live
#
# Drush site alias expansion is also done for the cp command:
#
#       cp -R @site1:%files @site2:%files
#
# When no drush site alias arguments are provided, the standard
# shell command behaves exactly the same as it usually does.
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
  s="$1"
  if [ -n "$s" ] && [ ${s:0:1} == "@" ] || [ ${s:0:1} == "%" ]
  then
    d="`drush drupal-directory $1 --local 2>/dev/null`"
    if [ $? == 0 ]
    then
      echo "cd $d";
      builtin cd "$d";
    else
      echo "Cannot cd to remote site $s"
    fi
  else
    builtin cd "$s";
  fi
}

# Works just like the `cd` shell alias above, with one additional
# feature: `cdd @remote-site` works like `ssh @remote-site`,
# whereas cd above will fail unless the site alias is local.  If
# you prefer the `ssh` behavior, you can rename this shell alias
# to `cd`.
function cdd() {
  s="$1"
  if [ -n "$s" ] && [ ${s:0:1} == "@" ] || [ ${s:0:1} == "%" ]
  then
    d="`drush drupal-directory $s 2>/dev/null`"
    `drush sa ${s%%:*} --component=remote-host > /dev/null 2>&1`
    if [ $? != 0 ]
    then
      echo "cd $d"
      builtin cd "$d"
    else
      if [ -n "$d" ]
      then
        c="cd \"$d\" \; bash"
        drush -s ssh ${s%%:*} --tty --escaped "$c"
        drush ssh ${s%%:*} --tty --escaped "$c"
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
function git() {
  s="$1"
  if [ -n "$s" ] && [ ${s:0:1} == "@" ] || [ ${a:0:1} == "%" ]
  then
    d="`drush drupal-directory $s 2>/dev/null`"
    `drush sa ${s%%:*} --component=remote-host > /dev/null 2>&1`
    if [ $? == 0 ]
    then
      ssh ${s%%:*} cd "$d" ; /usr/bin/git "${@:2}"
    else
      echo cd "$d" ; git "${@:2}"
      ( 
        cd "$d"
        git "${@:2}"
      )
    fi
  else
    `which git` "$@"
  fi  
}

# Get a directory listing on @site or @site:%files, etc, for local or remote sites.
function ls() {
  p=()
  r=
  for a in "$@" ; do
    if [ ${a:0:1} == "@" ] || [ ${a:0:1} == "%" ]
    then
      p[${#p[@]}]="`drush drupal-directory $a 2>/dev/null`"
      if [ ${a:0:1} == "@" ]
      then
        `drush sa ${a%:*} --component=remote-host > /dev/null 2>&1`
        if [ $? == 0 ]
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
    ssh $r ls "${p[@]}"
  else
    `which ls` "${p[@]}"
  fi
}

# Copy from or two @site or @site:%files, etc; local sites only.
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

# Show the drush directory location of @site:%files, etc; if the
# first parameter is not a site specification, then run the
# unix dd (convert and copy a file) command.
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
    "`which dd`" "${p[@]}"
  fi
}

# Escaping not quite working for args with spaces
function ssh_almost_working() {
  s="$1"
  if [ ${s:0:1} == "@" ]
  then
    shift
    c=
    for v in "$@" ; do
      c="$c \"$v\""
    done

    drush ssh -s --escaped $s "$c"
    drush ssh --escaped $s "$c"
  else
    echo `which ssh` "$@"
    "`which ssh`" "$@"
  fi 
}

# Closer, but escaping still not quite working for args with spaces
function ssh_also_almost_working() {
  s="$1"
  if [ ${s:0:1} == "@" ]
  then
    # Begin: rewrite $@ into an array a with each element enclosed in quotes
    
    a=()
    shift
    for v in "$@" ; do
      a[${#a[@]}]=\""$v"\"
    done
    
    # End: $@ now converted into quoted array a
    
    c="${a[@]}"
    drush ssh -s $s "$c"
    drush ssh $s "$c"
  else
    echo `which ssh` "$@"
    "`which ssh`" "$@"
  fi 
}

# Here is a complex ssh function that works with args with spaces.
function ssh() {
  d="$1"
  if [ ${d:0:1} == "@" ]
  then
    s="`drush -s ssh $d`"
    ssh_params="${s#* }"
    ssh_cmd="`which ssh`"

    # Begin: convert ssh_params into an array p

    c="$ssh_params "
    p=()
    while [ -n "$c" ] ; do
      v=
      hasvalue=true
      if [ "x${c:0:1}" = 'x"' ]
      then
        c="${c:1}"
        v="${c%%\"*}"
        c="${c#*\"}"
      elif [ "x${c:0:1}" = "x'" ]
      then
        c="${c:1}"
        v="${c%%\'*}"
        c="${c#*\'}"
      elif [ "x${c:0:1}" = "x " ]
      then
        c="${c:1}"
        hasvalue=false
      else
        v="${c%% *}"
        c="${c#* }"
      fi
      if $hasvalue
      then
        p[${#p[@]}]="$v"
      fi
    done

    # End: ssh_params now split into array p
    
    # Begin: rewrite $@ into an array a with each element enclosed in quotes
    
    a=()
    shift
    for v in "$@" ; do
      a[${#a[@]}]=\""$v"\"
    done
    
    # End: $@ now converted into quoted array a
    
    echo "$ssh_cmd" "${p[@]}" "${a[@]}"
    "$ssh_cmd" "${p[@]}" "${a[@]}"
  else
    echo `which ssh` "$@"
    "`which ssh`" "$@"
  fi 
}
