# sql:create

Create a database.

#### Examples

- <code>drush sql:create</code>. Create the database for the current site.
- <code>drush @site.test sql-create</code>. Create the database as specified for @site.test.
- <code>drush sql:create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"</code>. Create the database as specified in the db-url option.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --db-su=DB-SU**. Account to use when creating a new database.
- ** --db-su-pw=DB-SU-PW**. Password for the db-su account.

#### Aliases

- sql-create

