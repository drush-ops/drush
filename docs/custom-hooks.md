Custom Hooks
============

Drush commands can define custom events that other command files can hook. You can find examples in [CacheCommands](https://github.com/drush-ops/drush/blob/master/src/Commands/core/CacheCommands.php) and [SanitizeCommands](https://github.com/drush-ops/drush/blob/master/src/Drupal/Commands/sql/SanitizeCommands.php)

First, the command must implement CustomEventAwareInterface and use CustomEventAwareTrait, as described in the [dependency injection](dependency-injection.md) documentation.

Then, the command may ask the provided hook manager to return a list of handlers with a certain annotation. In the example below, the `my-event` label is used:
```
    /**
     * This command uses a custom event 'my-event' to collect data.  Note that
     * the event handlers will not be found unless the hook manager is
     * injected into this command handler object via `setHookManager()`
     * (defined in CustomEventAwareTrait).
     *
     * @command example:command
     */
    public function exampleCommand()
    {
        $myEventHandlers = $this->getCustomEventHandlers('my-event');
        $result = [];
        foreach ($myEventHandlers as $handler) {
            $result[] = $handler();
        }
        sort($result);
        return implode(',', $result);
    }
```

Other command handlers may provide implementations by implementing `@hook on-event my-event`.

```
    /**
     * @hook on-event my-event
     */
    public function hookOne()
    {
        return 'one';
    }
```
