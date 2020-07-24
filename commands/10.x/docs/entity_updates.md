# entity:updates

Apply pending entity schema updates.

#### Examples

- <code>drush updatedb:status --entity-updates | grep entity-update</code>. Use updatedb:status to detect pending updates.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --cache-clear[=CACHE-CLEAR]**. Set to 0 to suppress normal cache clearing; the caller should then clear if needed. [default: "1"]
- ** --no-cache-clear**. Negate --cache-clear option.

#### Aliases

- entup
- entity-updates

