# list

List available commands.

#### Examples

- <code>drush list</code>. List all commands.
- <code>drush list --filter=devel_generate</code>. Show only commands starting with devel-
- <code>drush list --format=xml</code>. List all commands in Symfony compatible xml format.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**.  [default: "listcli"]
- ** --raw**. Show a simple table of command names and descriptions.
- ** --filter=FILTER**. Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.

