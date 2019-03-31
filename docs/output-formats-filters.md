Output Formats, Fields and Filters
==================================

Drush utilizes a powerful formatting and filtering system that provides the user with a lot of control over how output from various commands is rendered.

* Output formats may be used to select the data type used to print the output. For example, many commands allow the user to select between a human-readable table, or various machine-parsable formats such as yaml and json.
* Output fields may be used to select and order the data columns.
* Output filters may be used to limit which data rows are printed based on logical expressions.

Output Formats
==============

The `--format` option may be used to select the data format used to print the output of a command. Most commands that produce informative output about some object or system can transform their data into different formats. For example, the Drush `version` command may be printed in a human-readable table (the default), or in a json array:
```
$ drush9 version
 Drush version : 9.5.0
$ drush9 version --format=json
{
    "drush-version": "9.5.0"
}
```
The available output formats are shown in the `help` for each command:
```
$ drush help version
Show drush version.

Options:
 --format=<json>    Select output format. Available: json, string, var_export, yaml. Default is key-value.
```

Output Fields
=============

If you wish to limit the number of columns produced by a command, use the `--fields` option. List the field names in the order they should be displayed:
```
$ drush9 views:list --fields=machine-name,status
+-------------------+----------+
| Machine name      | Status   |
+-------------------+----------+
| block_content     | Enabled  |
| comment           | Enabled  |
| comments_recent   | Enabled  |
| content           | Enabled  |
| content_recent    | Enabled  |
| files             | Enabled  |
| frontpage         | Enabled  |
| taxonomy_term     | Enabled  |
| user_admin_people | Enabled  |
| watchdog          | Enabled  |
| who_s_new         | Enabled  |
| who_s_online      | Enabled  |
| archive           | Disabled |
| glossary          | Disabled |
+-------------------+----------+
```
The available field names are shown in the `help` text:
```
$ drush9 help views:list
Get a list of all views in the system.

Options:
  --fields=FIELDS   Available fields: Machine name (machine-name),     
                    Name (label), Description (description), Status    
                    (status), Tag (tag) [default:                      
                    "machine-name,label,description,status"]           
```
Fields may be named either using their human-readable name, or via their machine name.

Note also that some commands do not display all of their available data columns by default. To show all available fields, use `--fields=*`

There is also a singluar form `--field` available. If this form is used, it will also force the output format to `string`.
```
$ drush9 views:list --field=machine-name 
block_content
comment
comments_recent
content
content_recent
files
frontpage
taxonomy_term
user_admin_people
watchdog
who_s_new
who_s_online
archive
glossary
```

Output Filters
==============

A number of Drush commands that output tabular data support a `--filter` option that allows rows from the output to be selected with simple logic expressions.

In its simplest form, the `--filter` option takes a string that indicates the value to filter by in the command's *default filter field*. For example, the `role:list` command's default filter field is `perms`; the output of the `role:list` command may be limited to only those roles that have a specified permission:
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
Finally, filter expressions may also use logical-and (`&&`) or logical-or (`||`) operations to separate multiple terms.  Parenthesis are not supported. For example, to search both the `title` and `severity` fields in the `core:requirements` command:
```
drush core:requirements --filter='title~=#(php|gd)#i&&severity=warning'
```

The `=` and `*=` operators always use case-insensitive comparisons. The `~=` operator is case-sensitive, unless the `i` [PCRE modifier](http://php.net/manual/en/reference.pcre.pattern.modifiers.php) is used, as shown in the previous example.

Comparison of Filters with Grep
-------------------------------

Using the `--filter` feature is similar to using `grep`. The main difference is that the filter feature does a semantic search, which is to say that it explicitly compares against the data in specific fields. In comparison, the `grep` command does a line-based search.

Show only results where the severity is "warning":

`drush core:requirements --filter='severity=warning'`

Show only lines that contain the string "warning" (either in the severity field, or somewhere else on the line):

`drush core:requirements | grep -i warning`

The table below compares and contrasts the two ways of searching.

| Feature                 | --filter            | grep                       |
| ----------------------- | ------------------- | -------------------------- |
| Regular expressions     | Yes, with `~=`      | Yes                        |
| Word-wrapped field data | Searched correctly  | Might cause false negative |
| Search just one field   | Yes                 | Might get false positives  |
| Search multiple fields  | Yes, with `||`/`&&` | Yes (line-based searching) |
| Searching hides header  | No                  | Yes (unless it matches)    |
