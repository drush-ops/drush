
## Core Hooks
All commandfiles may implement methods that are called by Drush at various times in the request cycle. To implement one, add a `#[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'pm:install')]` (for example) to the top of your method. The class constants for hooks are located in [HookManager](https://github.com/consolidation/annotated-command/blob/e01152f698eff4cb5df3ebfe5e097ef335dbd3c9/src/Hooks/HookManager.php#L30-L57). 

- [Documentation about available hooks](https://github.com/consolidation/annotated-command#hooks).
- To see how core commands implement a hook, you can [search the Drush source code](https://github.com/drush-ops/drush/search?q="%40#[CLI\Hook]"&type=Code&utf8=%E2%9C%93).

## Custom Hooks

Drush commands can define custom events that other command files can hook. You can find examples in [CacheCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/core/CacheCommands.php) and [SanitizeCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/sql/sanitize/SanitizeCommands.php)

First, the command must implement CustomEventAwareInterface and use CustomEventAwareTrait, as described in the [dependency injection](dependency-injection.md#inflection) documentation.

Then, the command may ask the provided hook manager to return a list of handlers with a certain attribute. In the example below, the `my-event` label is used:
```php
    /**
     * This command uses a custom event 'my-event' to collect data.  Note that
     * the event handlers will not be found unless the hook manager is
     * injected into this command handler object via `setHookManager()`
     * (defined in CustomEventAwareTrait).
     */
    #[CLI\Command(name: 'example:command')]  
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

Other commandfiles may provide implementations via a PHP8 Attribute or an Annotation.

=== "PHP8 Attributes"

    ```php
    /**
     * #[CLI\Hook(type: HookManager::ON_EVENT, target: 'my-event')]
     */
    public function hookOne()
    {
        return 'one';
    }
    ```

=== "Annotations"

    ```php
    /**
     * @hook on-event my-event
     */
    public function hookOne()
    {
        return 'one';
    }
    ```
