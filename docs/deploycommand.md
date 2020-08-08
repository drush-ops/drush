# Deploy

The [deploy command](commands/10.x/deploy.md) standardizes how Drupal deployments work. The intent is your 
deployment script updates the codebase for the target site and then this command 
performs the following:

```shell
drush updatedb --no-cache-clear
drush cache:rebuild
drush config:import
drush cache:rebuild
drush deploy:hook
```

## Authoring update functions
Below are the 3 types of update functions run by this command, in order. Choose the most appropriate for your need. 

| Function | Drupal API | Purpose |
| --- | --- | --- |
| [HOOK_update_n()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_update_N) | Not allowed | Low level changes. |
| [HOOK_post_update_NAME()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_post_update_NAME) | Allowed | Runs *before* config is imported. |
| [HOOK_deploy_NAME()](https://github.com/drush-ops/drush/blob/10.x/tests/functional/resources/modules/d8/woot/woot.deploy.php) | Allowed | Runs *after* config is imported. | 

## Configuration

If you need to customize this command, you should use Drush configuration for the 
subcommands listed above (e.g. [updatedb](commands/10.x/updatedb.md), [config:import](commands/10.x/config_import.md), etc.).
