Drush Configuration
===================

Drush users may provide configuration via: 

1. yml files that are placed in specific directories. [See our example file](https://raw.githubusercontent.com/drush-ops/drush/10.x/examples/example.drush.yml) for more information. You may also add configuration to a site alias - [see example site alias](https://raw.githubusercontent.com/drush-ops/drush/10.x/examples/example.site.yml).
1. Properly named environment variables are automatically used as configuration. To populate the options.uri config item, create an environment variable like so `DRUSH_OPTIONS_URI=http://example.com`. As you can see, variable names should be uppercased, prefixed with `DRUSH_`, and periods replaced with dashes. 

If you are authoring a commandfile and wish to access the user's configuration, see [Command Authoring](commands.md).

The Drush configuration system has been factored out of Drush and shared with the world at [https://github.com/consolidation/config](https://github.com/consolidation/config). Feel free to use it for your projects. Lots more usage information is there.
