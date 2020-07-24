# image:flush

Flush all derived images for a given style.

#### Examples

- <code>drush image:flush</code>. Pick an image style and then delete its derivatives.
- <code>drush image:flush thumbnail,large</code>. Delete all thumbnail and large derivatives.
- <code>drush image:flush --all</code>. Flush all derived images. They will be regenerated on demand.

#### Arguments

- **style_names**. A comma delimited list of image style machine names. If not provided, user may choose from a list of names.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --all**. Flush all derived images

#### Aliases

- if
- image-flush

