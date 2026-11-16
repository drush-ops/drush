Drush can be run in your shell by typing `dr` from within your project root directory or anywhere within Drupal.

    $ dr [options] <command> [argument1] [argument2]

Use the [help command](commands/help.md) to get a list of available options and commands:

    $ dr help pm:list

Drush needs to be told the domain of your site in order for commands to generate correct links (e.g. user:login). You may set a DRUPAL_URL environment variable, or use the --url option.

    $ dr --url=http://example.com user:login
