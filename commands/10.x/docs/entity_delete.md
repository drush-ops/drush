# entity:delete

Delete content entities.

To delete configuration entities, see config:delete command.

#### Examples

- <code>drush entity:delete node --bundle=article</code>. Delete all article entities.
- <code>drush entity:delete shortcut</code>. Delete all shortcut entities.
- <code>drush entity:delete node 22,24</code>. Delete nodes 22 and 24.
- <code>drush entity:delete node --exclude=9,14,81</code>. Delete all nodes except node 9, 14 and 81.
- <code>drush entity:delete user</code>. Delete all users except uid=1.

#### Arguments

- **entity_type**. An entity machine name.
- **ids**. A comma delimited list of Ids.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --bundle=BUNDLE**. Restrict deletion to the specified bundle. Ignored when ids is specified.
- ** --exclude=EXCLUDE**. Exclude certain entities from deletion. Ignored when ids is specified.

#### Aliases

- edel
- entity-delete

