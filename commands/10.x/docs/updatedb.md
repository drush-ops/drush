# updatedb

Apply any database updates required (as with running update.php).

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --cache-clear[=CACHE-CLEAR]**. Clear caches upon completion. [default: "1"]
- ** --entity-updates**. Run automatic entity schema updates at the end of any update hooks. Not supported in Drupal >= 8.7.0.
- ** --post-updates[=POST-UPDATES]**. Run post updates after hook_update_n and entity updates. [default: "1"]
- ** --no-cache-clear**. Negate --cache-clear option.
- ** --no-post-updates**. Negate --post-updates option.

#### Aliases

- updb

