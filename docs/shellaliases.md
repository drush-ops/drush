Drush Shell Aliases
===================

A Drush shell alias is a shortcut to any Drush command or any shell command. Drush shell aliases are very similar to [git aliases](https://git.wiki.kernel.org/index.php/Aliases\#Advanced).

A shell alias is defined in a Drush configuration file called drushrc.php. See `drush topic docs-configuration`. There are two kinds of shell aliases: an alias whose value begins with a '!' will execute the rest of the line as bash commands. Aliases that do not start with a '!' will be interpreted as Drush commands.

        $options['shell-aliases']['pull'] = '!git pull';
        $options['shell-aliases']['noncore'] = 'pm-list --no-core';

With the above two aliases defined, `drush pull` will then be equivalent to `git pull`, and `drush noncore` will be equivalent to `drush pm-list --no-core`.

Shell Alias Replacements
------------------------

Shell aliases are even more powerful when combined with shell alias replacements and site aliases. Shell alias replacements take the form of {{sitealias-item}} or {{%pathalias-item}}, and also the special {{@target}}, which is replaced with the name of the site alias used, or '@none' if none was used.

For example, given the following site alias:

         $aliases['dev'] = array (
           'root' => '/path/to/drupal',
           'uri' => 'http://example.com',
           '#live' => '@acme.live',
         );

The alias below can be used for all your projects to fetch the database and files from the client's live site via `drush @dev pull-data`. Note that these aliases assume that the alias used defines an item named '\#live' (as shown in the above alias).

    $options['shell-aliases'] = array( 
      'pull-data' => '!drush sql-sync {{#live}} {{@target}} && drush rsync {{#live}}:%files {{@target}}:%files'
    );

If the user does not use these shell aliases with any site alias, then an error will be returned and the script will not run. These aliases with replacements can be used to quickly run combinations of drush sql-sync and rsync commands on the "standard" source or target site, reducing the risk of typos that might send information in the wrong direction or to the wrong site.

