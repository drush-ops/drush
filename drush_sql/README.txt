// $Id$

DESCRIPTION
-----------

drush_sql.module (The Drupal Shell SQL Manager) allows you to interact with the database from the command line.

The two most interesting commands are:

sql query: execute a query against the site database

sql dump: Exports the Drupal DB as SQL using mysqldump or pg_dump.


TIPS
------------
Using the -l command line option for drush.php, you can easily run SQL commands against any site in your multisite installation.

CREDITS
------------
Authored by  Arto Bendiken <http://bendiken.net/>. 
Maintained by Moshe Weitzman <weitzman AT tejasa DOT com>