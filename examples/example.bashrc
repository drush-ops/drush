# Examples of valid statements for a drush bashrc file. Use this file to cut down on
# typing of options and avoid mistakes.
#
# Rename this file to .bashrc and optionally copy it to one of
# four convenient places:
#
# 1. User's $HOME folder (i.e. ~/.bashrc).
# 2. User's .drush folder (i.e. ~/.drush/.bashrc).
# 3. System wide configuration folder (e.g. /etc/drush/.bashrc).
# 4. System wide command folder (e.g. /usr/share/drush/command/.bashrc).
# 5. Drush installation folder
#
# Drush will search for .bashrc files whenever the drush interactive
# shell, i.e. `drush core-cli` is entered.   If a configuration file 
# is found in any of the above locations, it will be sourced by bash 
# and merged with other configuration files encountered.

alias siwef='site-install wef --account-name=super --account-mail=me@wef'
alias dump='sql-dump --structure-tables-key=wef --ordered-dump'
alias cli-update='(drush core-cli --pipe > $HOME/.bash_aliases) && source $HOME/.bash_aliases'
