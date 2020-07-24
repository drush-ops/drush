# cache:clear

Clear a specific cache, or all Drupal caches.

#### Examples

- <code>drush cc bin entity,bootstrap</code>. Clear the entity and bootstrap cache bins.

#### Arguments

- **type**. The particular cache to clear. Omit this argument to choose from available types.
- **args**. Additional arguments as might be expected (e.g. bin name).

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --cache-clear[=CACHE-CLEAR]**. Set to 0 to suppress normal cache clearing; the caller should then clear if needed. [default: "1"]
- ** --no-cache-clear**. Negate --cache-clear option.

#### Aliases

- cc
- cache-clear

