Drush Output Formats
====================

Many Drush commands produce output that may be rendered in a variety of different ways using a pluggable formatting system. Drush commands that support output formats will show a --format option in their help text. The available formats are also listed in the help text, along with the default value for the format option. The list of formats shown is abbreviated; to see the complete list of available formats, run the help command with the --verbose option.

The --pipe option is a quick, consistent way to get machine readable output from a command, in whatever way the command author thought was helpful. The --pipe option is equivalent to using --format=`<pipe-format>` The pipe format will be shown in the options section of the command help, under the --pipe option. For historic reasons, --pipe also hides all log messages.

To best understand how the various Drush output formatters work, it is best to first look at the output of the command using the 'var\_export' format. This will show the result of the command using the exact structure that was built by the command, without any reformatting. This is the standard format for the Drush command. Different formatters will take this information and present it in different ways.

Global Options
--------------

-   --list-separator: Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
-   --line-separator: In nested lists of lists, specify how the outer lists ("lines") should be separated.

Output Formats
--------------

A list of available formats, and their affect on the output of certain Drush commands, is shown below.

