# Introduction

The deploy command standardizes how Drupal deployments work. The intent is your 
deployment script updates the codebase for the target site and then this command 
performs the following:

```
drush updatedb
drush config:import
drush deploy:hook
drush cache:rebuild
```

# Authoring update functions
Below are the 3 types of update functions run by this command. Choose the most appropriate for your need. 

| Function | Purpose |
| --- | --- |
| [hook_update_n()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_update_N) | Low level changes. Drupal API not allowed. |
| [hook_post_update_NAME()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_post_update_NAME) | Drupal API allowed. |
| hook_deploy() | Runs after config is imported. Drupal API allowed. | 

## Configuration

If you need to customize this command, you should use Drush configuration for the 
subcommands listed above (e.g. updatedb, config:import, etc.).