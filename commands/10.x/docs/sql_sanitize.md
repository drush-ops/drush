# sql:sanitize

Sanitize the database by removing or obfuscating user data.

Commandfiles may add custom operations by implementing:
- @hook_on-event sql-sanitize-message
Display summary to user before confirmation.
- @hook post-command sql-sanitize
Run queries or call APIs to perform sanitizing

#### Examples

- <code>drush sql:sanitize --sanitize-password=no</code>. Sanitize database without modifying any passwords.
- <code>drush sql:sanitize --allowlist-fields=field_biography,field_phone_number</code>. Sanitizes database but exempts two user fields from modification.

#### Aliases

- sqlsan
- sql-sanitize

