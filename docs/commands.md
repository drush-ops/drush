Creating Custom Drush Commands
==============================

Creating a new Drush command is very easy. Follow these simple steps:

1.  Create a command file called COMMANDFILE.drush.inc
1.  Implement the function COMMANDFILE\_drush\_command()
1.  Implement the functions that your commands will call. These will usually be named drush\_COMMANDFILE\_COMMANDNAME().

For an example Drush command, see examples/sandwich.drush.inc. The steps for implementing your command are explained in more detail below.

Create COMMANDFILE.drush.inc
----------------------------

The name of your Drush command is very important. It must end in ".drush.inc" to be recognized as a Drush command. The part of the filename that comes before the ".drush.inc" becomes the name of the commandfile. Optionally, the commandfile may be restricted to a particular version of Drupal by adding a ".dVERSION" after the name of the commandfile (e.g. ".d8.drush.inc") Your commandfile name is used by Drush to compose the names of the functions it will call, so choose wisely.

The example Drush command, 'make-me-a-sandwich', is stored in the 'sandwich' commandfile, 'sandwich.Drush.inc'. You can find this file in the 'examples' directory in the Drush distribution.

Drush searches for commandfiles in the following locations:

-   Folders listed in the 'include' option (see `drush topic docs-configuration`).
-   The system-wide Drush commands folder, e.g. /usr/share/drush/commands
-   The ".drush" folder in the user's HOME folder.
-   /drush and /sites/all/drush in the current Drupal installation
-   All enabled modules in the current Drupal installation
-   Folders and files containing other versions of Drush in their names will be \*skipped\* (e.g. devel.drush4.inc or drush4/devel.drush.inc). Names containing the current version of Drush (e.g. devel.drush5.inc) will be loaded.

Note that modules in the current Drupal installation will only be considered if Drush has bootstrapped to at least the DRUSH\_BOOSTRAP\_SITE level. Usually, when working with a Drupal site, Drush will bootstrap to DRUSH\_BOOTSTRAP\_FULL; in this case, only the Drush commandfiles in enabled modules will be considered eligible for loading. If Drush only bootstraps to DRUSH\_BOOTSTRAP\_SITE, though, then all Drush commandfiles will be considered, whether the module is enabled or not. See `drush topic docs-bootstrap` for more information on bootstrapping.

Implement COMMANDFILE\_drush\_command()
---------------------------------------

The drush\_command hook is the most important part of the commandfile. It returns an array of items that define how your commands should be called, and how they work. Drush commands are very similar to the Drupal menu system. The elements that can appear in a Drush command definition are shown below.

-   **aliases**: Provides a list of shorter names for the command. For example, pm-download may also be called via `drush dl`. If the alias is used, Drush will substitute back in the primary command name, so pm-download will still be used to generate the command hook, etc.
-   **command-hook**: Change the name of the function Drush will call to execute the command from drush\_COMMANDFILE\_COMMANDNAME() to drush\_COMMANDFILE\_COMMANDHOOK(), where COMMANDNAME is the original name of the command, and COMMANDHOOK is the value of the 'command-hook' item.
-   **callback**: Name of function to invoke for this command. The callback function name \_must\_ begin with "drush\_commandfile\_", where commandfile is from the file "commandfile.drush.inc", which contains the commandfile\_drush\_command() function that returned this command. Note that the callback entry is optional; it is preferable to omit it, in which case drush\_invoke() will generate the hook function name.
-   **callback arguments**: An array of arguments to pass to the callback. The command line arguments, if any, will appear after the callback arguments in the function parameters.
-   **description**: Description of the command.
-   **arguments**: An array of arguments that are understood by the command. Used by `drush help` only.
-   **required-arguments**: Defaults to FALSE; TRUE if all of the arguments are required. Set to an integer count of required arguments if only some are required.
-   **options**: An array of options that are understood by the command. Any option that the command expects to be able to query via drush\_get\_option \_must\_ be listed in the options array. If it is not, users will get an error about an "Unknown option" when they try to specify the option on the command line.

    The value of each option may be either a simple string containing the option description, or an array containing the following information:

    -   **description**: A description of the option.
    -   **example-value**: An example value to show in help.
    -   **value**: optional|required.
    -   **required**: Indicates that an option must be provided.
    -   **hidden**: The option is not shown in the help output (rare).

-   **allow-additional-options**: If TRUE, then the strict validation to see if options exist is skipped. Examples of where this is done includes the core-rsync command, which passes options along to the rsync shell command. This item may also contain a list of other commands that are invoked as subcommands (e.g. the pm-update command calls pm-updatecode and updatedb commands). When this is done, the options from the subcommand may be used on the commandline, and are also listed in the command's `help` output. Defaults to FALSE.
-   **examples**: An array of examples that are understood by the command. Used by `drush help` only.
-   **scope**: One of 'system', 'project', 'site'. Not currently used.
-   **bootstrap**: Drupal bootstrap level. More info at `drush topic docs-bootstrap`. Valid values are:
    -   DRUSH\_BOOTSTRAP\_NONE
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_ROOT
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_SITE
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_CONFIGURATION
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_DATABASE
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_FULL
    -   DRUSH\_BOOTSTRAP\_DRUPAL\_LOGIN (default)
    -   DRUSH\_BOOTSTRAP\_MAX
-   **core**: Drupal major version required. Append a '+' to indicate 'and later versions.'
-   **drupal dependencies**: Drupal modules required for this command.
-   **drush dependencies**: Other Drush commandfiles required for this command.
-   **engines**: Provides a list of Drush engines to load with this command. The set of appropriate engines varies by command.
    -   **outputformat**: One important engine is the 'outputformat' engine. This engine is responsible for formatting the structured data (usually an associative array) that a Drush command returns as its function result into a human-readable or machine-parsable string. Some of the options that may be used with output format engines are listed below; however, each specific output format type can take additional option items that control the way that the output is rendered. See the comment in the output format's implementation for information. The Drush core output format engines can be found in commands/core/outputformat.
        -   **default**: The default type to render output as. If declared, the command should not print any output on its own, but instead should return a data structure (usually an associative array) that can be rendered by the output type selected.
        -   **pipe-format**: When the command is executed in --pipe mode, the command output will be rendered by the format specified by the pipe-format item instead of the default format. Note that in either event, the user may specify the format to use via the --format command-line option.
        -   **formatted-filter** and **pipe-filter**: Specifies a function callback that will be used to filter the command result. The filter is selected based on the type of output format object selected. Most output formatters are 'pipe' formatters, that produce machine-parsable output. A few formatters, such as 'table' and 'key-value' are 'formatted' filter types, that produce human-readable output.
-   **topics**: Provides a list of topic commands that are related in some way to this command. Used by `drush help`.
-   **topic**: Set to TRUE if this command is a topic, callable from the `drush docs-topics` command.
-   **category**: Set this to override the category in which your command is listed in help.

The 'sandwich' drush\_command hook looks like this:

            function sandwich_drush_command() {
              $items = array();

              $items['make-me-a-sandwich'] = array(
                'description' => "Makes a delicious sandwich.",
                'arguments' => array(
                  'filling' => 'The type of the sandwich (turkey, cheese, etc.)',
                ),
                'options' => array(
                  'spreads' => 'Comma delimited list of spreads (e.g. mayonnaise, mustard)',
                ),
                'examples' => array(
                  'drush mmas turkey --spreads=ketchup,mustard' => 'Make a terrible-tasting sandwich that is lacking in pickles.',
                ),
                'aliases' => array('mmas'),
                'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
              );

              return $items;
            }

Most of the items in the 'make-me-a-sandwich' command definition have no effect on execution, and are used only by `drush help`. The exceptions are 'aliases' (described above) and 'bootstrap'. As previously mentioned, `drush topic docs-bootstrap` explains the Drush bootstrapping process in detail.

Implement drush\_COMMANDFILE\_COMMANDNAME()
-------------------------------------------

The 'make-me-a-sandwich' command in sandwich.drush.inc is defined as follows:

        function drush_sandwich_make_me_a_sandwich($filling = 'ascii') {
          // implementation here ...
        }

If a user runs `drush make-me-a-sandwich` with no command line arguments, then Drush will call drush\_sandwich\_make\_me\_a\_sandwich() with no function parameters; in this case, $filling will take on the provided default value, 'ascii'. (If there is no default value provided, then the variable will be NULL, and a warning will be printed.) Running `drush make-me-a-sandwich ham` will cause Drush to call drush\_sandwich\_make\_me\_a\_sandwich('ham'). In the same way, commands that take two command line arguments can simply define two functional parameters, and a command that takes a variable number of command line arguments can use the standard php function func\_get\_args() to get them all in an array for easy processing.

It is also very easy to query the command options using the function drush\_get\_option(). For example, in the drush\_sandwich\_make\_me\_a\_sandwich() function, the --spreads option is retrieved as follows:

            $str_spreads = '';
            if ($spreads = drush_get_option('spreads')) {
              $list = implode(' and ', explode(',', $spreads));
              $str_spreads = ' with just a dash of ' . $list;
            }

Note that Drush will actually call a sequence of functions before and after your Drush command function. One of these hooks is the "validate" hook. The 'sandwich' commandfile provides a validate hook for the 'make-me-a-sandwich' command:

            function drush_sandwich_make_me_a_sandwich_validate() {
              $name = posix_getpwuid(posix_geteuid());
              if ($name['name'] !== 'root') {
                return drush_set_error('MAKE_IT_YOUSELF', dt('What? Make your own sandwich.'));
              }
            }

The validate function should call drush\_set\_error() and return its result if the command cannot be validated for some reason. See `drush topic docs-policy` for more information on defining policy functions with validate hooks, and `drush topic docs-api` for information on how the command hook process works. Also, the list of defined drush error codes can be found in `drush topic docs-errorcodes`.

To see the full implementation of the sample 'make-me-a-sandwich' command, see `drush topic docs-examplecommand`.

