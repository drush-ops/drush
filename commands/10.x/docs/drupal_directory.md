# drupal:directory

Return the filesystem path for modules/themes and other key folders.

#### Examples

- <code>cd `drush dd devel`</code>. Navigate into the devel module directory
- <code>cd `drush dd`</code>. Navigate to the root of your Drupal site
- <code>cd `drush dd files`</code>. Navigate to the files directory.
- <code>drush dd @alias:%files</code>. Print the path to the files directory on the site @alias.
- <code>edit `drush dd devel`/devel.module</code>. Open devel module in your editor (customize 'edit' for your editor)

#### Arguments

- **target**. A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --local-only**. Reject any target that specifies a remote site.
- ** --component[=COMPONENT]**. The portion of the evaluated path to return. Defaults to 'path'; 'name' returns the site alias of the target.

#### Aliases

- dd
- drupal-directory

