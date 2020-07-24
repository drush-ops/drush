# role:perm:add

Grant specified permission(s) to a role.

#### Examples

- <code>drush role-add-perm anonymous 'post comments'</code>. Allow anon users to post comments.
- <code>drush role:add-perm anonymous "'post comments','access content'"</code>. Allow anon users to post comments and access content.
- <code>drush pm:info --fields=permissions --format=csv aggregator</code>. Discover the permissions associated with given module (then use this command as needed).

#### Arguments

- **machine_name**. The role to modify.
- **permissions**. The list of permission to grant, delimited by commas.

#### Aliases

- rap
- role-add-perm

