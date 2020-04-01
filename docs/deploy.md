# Introduction

The deploy command standardizes how Drupal deployments work. The intent is your 
deployment script updates the codebase for the target site and then this command 
performs the following:

```
drush updatedb --no-cache-clear
drush cache:rebuild
drush config:import
drush cache:rebuild
drush deploy:hook
```

# Authoring update functions
Below are the 3 types of update functions run by this command. Choose the most appropriate for your need. 

| Function | Purpose |
| --- | --- |
| [HOOK_update_n()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_update_N) | Low level changes. Drupal API not allowed. |
| [HOOK_post_update_NAME()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_post_update_NAME) | Drupal API allowed. |
| [HOOK_deploy_NAME()](https://github.com/drush-ops/drush/blob/master/tests/functional/resources/modules/d8/woot/woot.deploy.php) | Runs after config is imported. Drupal API allowed. | 

## Configuration

If you need to customize this command, you should use Drush configuration for the 
subcommands listed above (e.g. updatedb, config:import, etc.).