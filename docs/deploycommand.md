# Deploy
:octicons-tag-24: 10.3+

The [deploy command](commands/deploy.md) standardizes how Drupal deployments work. The intent is your 
deployment script updates the codebase for the target site and then this command 
performs the following:

```shell
drush updatedb
drush config:import
drush cache:rebuild
drush deploy:hook
drush cache:warm (Drupal 11.2+)
```

## Authoring update functions
Below are the 3 types of update functions run by this command, in order. Choose the most appropriate for your
need. **Exercise caution when implementing `HOOK_update_N()`** — the full API isn't available to you, see [the
documentation][HOOK_update_N()] for more details.


| Function | Provided by | Purpose |
| --- | --- | --- |
| [HOOK_update_N()] | Drupal | Low level changes. |
| [HOOK_post_update_NAME()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_post_update_NAME) | Drupal | Runs *before* config is imported. |
| [HOOK_deploy_NAME()](https://github.com/drush-ops/drush/tree/HEAD/drush.api.php) | Drush | Runs *after* config is imported. | 

## Configuration

If you need to customize this command, you should use Drush configuration for the 
subcommands listed above (e.g. [updatedb](commands/updatedb.md), [config:import](commands/config_import.md), etc.).

[HOOK_update_N()]: https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_update_N
