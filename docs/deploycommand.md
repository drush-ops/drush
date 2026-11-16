# Deploy
:octicons-tag-24: 10.3+

The [deploy command](commands/deploy.md) standardizes how Drupal deployments work. The intent is your 
deployment script updates the codebase for the target site and then this command 
performs the following:

```shell
dr updatedb
dr config:import
dr cache:rebuild
dr deploy:hook
dr cache:warm
```

The steps run in one process, non-interactively, with their default options. If a step fails, the deployment stops.

## Authoring update functions
Below are the 3 types of update functions run by this command, in order. Choose the most appropriate for your
need. **Exercise caution when implementing `HOOK_update_N()`** — the full API isn't available to you, see [the
documentation][HOOK_update_N()] for more details.


| Function | Provided by | Purpose |
| --- | --- | --- |
| [HOOK_update_N()] | Drupal | Low level changes. |
| [HOOK_post_update_NAME()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_post_update_NAME) | Drupal | Runs *before* config is imported. |
| [HOOK_deploy_NAME()](https://github.com/drush-ops/drush/blob/15.x/drush.api.php) | Drush | Runs *after* config is imported. | 

## Customizing

`deploy` takes no options for its steps. If you need different options, run the steps yourself in your deployment script, in the order above.

[HOOK_update_N()]: https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Extension!module.api.php/function/hook_update_N
