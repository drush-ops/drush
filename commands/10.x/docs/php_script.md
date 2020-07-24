# php:script

Run php a script after a full Drupal bootstrap.

A useful alternative to eval command when your php is lengthy or you
can't be bothered to figure out bash quoting. If you plan to share a
script with others, consider making a full Drush command instead, since
that's more self-documenting.  Drush provides commandline options to the
script via a variable called $extra.

#### Examples

- <code>drush php:script example --script-path=/path/to/scripts:/another/path</code>. Run a script named example.php from specified paths
- <code>drush php:script -</code>. Run PHP code from standard input.
- <code>drush php:script</code>. List all available scripts.
- <code>drush php:script foo -- apple --cider</code>. Run foo.php script with argument 'apple' and option 'cider'. Note the -- separator.

#### Arguments

- **extra**. 

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**.  [default: "var_export"]
- ** --script-path=SCRIPT-PATH**. Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).

#### Aliases

- scr
- php-script

