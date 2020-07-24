# core:init

Enrich the bash startup file with bash aliases and a smart command prompt.

#### Examples

- <code>core-init --edit</code>. Enrich Bash and open drush config file in editor.
- <code>core-init --edit --bg</code>. Return to shell prompt as soon as the editor window opens

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --edit**. Open the new config file in an editor.
- ** --add-path[=ADD-PATH]**. Always add Drush to the $PATH in the user's .bashrc file, even if it is already in the $PATH. Use --no-add-path to skip updating .bashrc with the Drush $PATH. Default is to update .bashrc only if Drush is not already in the $PATH.

#### Aliases

- init
- core-init

