# php:eval

Evaluate arbitrary php code after bootstrapping Drupal (if available).

#### Examples

- <code>drush php:eval 'variable_set("hello", "world");'</code>. Sets the hello variable using Drupal API.'
- <code>drush php:eval '$node = node_load(1); print $node->title;'</code>. Loads node with nid 1 and then prints its title.
- <code>drush php:eval "file_unmanaged_copy(\'$HOME/Pictures/image.jpg\', \'public://image.jpg\');"</code>. Copies a file whose path is determined by an environment\'s variable. Note the use of double quotes so the variable $HOME gets replaced by its value.
- <code>drush php:eval "node_access_rebuild();"</code>. Rebuild node access permissions.

#### Arguments

- **code**. PHP code

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**.  [default: "var_export"]

#### Aliases

- eval
- ev
- php-eval

