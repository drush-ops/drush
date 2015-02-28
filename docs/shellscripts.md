Drush Shell Scripts
===================

A drush shell script is any Unix shell script file that has its "execute" bit set (i.e., via `chmod +x myscript.drush`) and that begins with a specific line:

        #!/usr/bin/env drush

or

        #!/full/path/to/drush

The former is the usual form, and is more convenient in that it will allow you to run the script regardless of where drush has been installed on your system, as long as it appears in your PATH. The later form allows you to specify the drush command add options to use, as in:

        #!/full/path/to/drush php-script --some-option

Adding specific options is important only in certain cases, described later; it is usually not necessary.

Drush scripts do not need to be named "\*.drush" or "\*.script"; they can be named anything at all. To run them, make sure they are executable (`chmod +x helloworld.script`) and then run them from the shell like any other script.

There are two big advantages to drush scripts over bash scripts:

-   They are written in php
-   drush can bootstrap your Drupal site before running your script.

To bootstrap a Drupal site, provide an alias to the site to bootstrap as the first commandline argument.

For example:

        $ helloworld.script @dev a b c

If the first argument is a valid site alias, drush will remove it from the arument list and bootstrap that site, then run your script. The script itself will not see @dev on its argument list. If you do not want drush to remove the first site alias from your scripts argument list (e.g. if your script wishes to syncronise two sites, specified by the first two arguments, and does not want to bootstrap either of those two sites), then fully specify the drush command (php-script) and options to use, as shown above. By default, if the drush command is not specified, drush will provide the following default line:

        #!/full/path/to/drush php-script --bootstrap-to-first-arg

It is the option --bootstrap-to-first-arg that causes drush to pull off the first argument and bootstrap it. The way to get rid of that option is to specify the php-script line to run, and leave it off, like so:

        #!/full/path/to/drush php-script

Note that 'php-script' is the only built-in drush command that makes sense to put on the "shebang" ("\#!" is pronounced "shebang") line. However, if you wanted to, you could implement your own custom version of php-script (e.g. to preprocess the script input, perhaps), and specify that command on the shebang line.

Drush scripts can access their arguments via the drush\_shift() function:

            while ($arg = drush_shift()) {
              drush_print($arg);
            }

Options are available via drush\_get\_option('option-name'). The directory where the script was launched is available via drush_cwd()

See the example drush script in `drush topic docs-examplescript`, and the list of drush error codes in `drush topic docs-errorcodes`.

