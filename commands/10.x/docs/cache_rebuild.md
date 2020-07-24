# cache:rebuild

Rebuild a Drupal 8 site.

This is a copy of core/rebuild.php. Additionally
it also clears Drush cache and Drupal's render cache.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --cache-clear[=CACHE-CLEAR]**. Set to 0 to suppress normal cache clearing; the caller should then clear if needed. [default: "1"]
- ** --no-cache-clear**. Negate --cache-clear option.

#### Aliases

- cr
- rebuild
- cache-rebuild

