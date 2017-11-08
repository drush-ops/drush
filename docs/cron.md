Running Drupal cron tasks from Drush
====================================

Drupal cron tasks are often set up to be run via a wget call to cron.php; this same task can also be accomplished via the `drush cron` command, which circumvents the need to provide a web server interface to cron.

Quick start
----------

If you just want to get started quickly, here is a crontab entry that will run cron once every hour at ten minutes after the hour:

    10 * * * * /usr/bin/env PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin COLUMNS=72 cd [DOCROOT] && ../vendor/bin/drush --uri=your.drupalsite.org --quiet cron

You should set up crontab to run your cron tasks as the same user that runs the web server; for example, if you run your web server as the user www-data:

    sudo crontab -u www-data -e

You might need to edit the crontab entry shown above slightly for your particular setup; for example, if you have installed Drush to some directory other than /usr/local/drush, then you will need to adjust the path to drush appropriately. We'll break down the meaning of each section of the crontab entry in the documentation that continues below.

Setting the schedule
--------------------

See `man 5 crontab` for information on how to format the information in a crontab entry. In the example above, the schedule for the crontab is set by the string `10 * * * *`. These fields are the minute, hour, day of month, month and day of week; `*` means essentially 'all values', so `10 * * * *` will run any time the minute == 10 (once every hour).

Setting the PATH
----------------

We use /usr/bin/env to run Drush so that we can set up some necessary environment variables that Drush needs to execute. By default, cron will run each command with an empty PATH, which would not work well with Drush. To find out what your PATH needs to be, just type:

    echo $PATH

Take the value that is output and place it into your crontab entry in the place of the one shown above. You can remove any entry that is known to not be of interest to Drush (e.g. /usr/games), or is only useful in a graphic environment (e.g. /usr/X11/bin).

Setting COLUMNS
---------------

When running Drush in a terminal, the number of columns will be automatically determined by Drush by way of the tput command, which queries the active terminal to determine what the width of the screen is. When running Drush from cron, there will not be any terminal set, and the call to tput will produce an error message. Spurious error messages are undesirable, as cron is often configured to send email whenever any output is produced, so it is important to make an effort to insure that successful runs of cron complete with no output.

In some cases, Drush is smart enough to recognize that there is no terminal -- if the terminal value is empty or "dumb", for example. However, there are some "non-terminal" values that Drush does not recognize, such as "unknown." If you manually set `COLUMNS`, then Drush will respect your setting and will not attempt to call tput.

Using --quiet
-------------

By default, Drush will print a success message when the run of cron is completed. The --quiet flag will suppress these and other progress messages, again avoiding an unnecessary email message.

Specifying the Drupal site to run
---------------------------------

There are many ways to tell Drush which Drupal site to select for the active command, and any may be used here. The example uses `cd [DOCROOT]`, but you could also use the --root and --uri flags.

