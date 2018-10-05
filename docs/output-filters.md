Output Filters
==============

A number of Drush commands that output tabular data support a `--filter` option that allows rows from the output to be selected with simple logic expressions.

In its simplest form, the `--filter` option takes a simple string that indicates the value to filter by in the command's *default filter field*. For example, the `role:list` command's default filter field is `perms`; the output of the `role:list` command can be limited to only those roles that have a specified permission:
```
$ drush role:list --filter='post comments'
authenticated:
  label: 'Authenticated user'
  perms:
    - 'access comments'
    - 'access content'
    - 'access shortcuts'
    - 'access site-wide contact form'
    - 'access user contact forms'
    - 'post comments'
    - 'search content'
    - 'skip comment approval'
    - 'use text format basic_html'
```
Note that not all commands have a default filter field.

Other fields in the output may be searched by using a simple expression in the `--filter` term. For example, to list only the enabled extensions with the `pm:list` command, you could run:
```
$ drush pm:list --filter='status=enabled'
```
To search for fields that contain a string using the operator `*=`, or match a regular expression with the `~=` operator. For example, to find all views whose machine name contains the word "content":
```
drush views:list --filter='machine-name*=content'
```
To use a regular expression to find any core requirement notice whose title contains either "php" or "gd"
```
drush core:requirements --filter='title~=#(php|gd)#i'
```
Finally, filter expressions may also use logical-and (`&&`) or logical-or (`||`) operations to separate multiple terms.  Parenthesis are not supported.
