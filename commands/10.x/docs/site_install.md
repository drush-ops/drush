# site:install

Install Drupal along with modules/themes/configuration/profile.

#### Examples

- <code>drush si expert --locale=uk</code>. (Re)install using the expert install profile. Set default language to Ukrainian.
- <code>drush si --db-url=mysql://root:pass@localhost:port/dbname</code>. Install using the specified DB params.
- <code>drush si --db-url=sqlite://sites/example.com/files/.ht.sqlite</code>. Install using SQLite
- <code>drush si --account-pass=mom</code>. Re-install with specified uid1 password.
- <code>drush si --existing-config</code>. Install based on the yml files stored in the config export/import directory.
- <code>drush si standard install_configure_form.enable_update_status_emails=NULL</code>. Disable email notification during install and later. If your server has no mail transfer agent, this gets rid of an error during install.

#### Arguments

- **profile**. An install profile name. Defaults to 'standard' unless an install profile is marked as a distribution. Additional info for the install profile may also be provided with additional arguments. The key is in the form [form name].[parameter name]

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --db-url=DB-URL**. A Drupal 6 style database URL. Required for initial install, not re-install. If omitted and required, Drush prompts for this item.
- ** --db-prefix=DB-PREFIX**. An optional table prefix to use for initial install.
- ** --db-su=DB-SU**. Account to use when creating a new database. Must have Grant permission (mysql only). Optional.
- ** --db-su-pw=DB-SU-PW**. Password for the "db-su" account. Optional.
- ** --account-name[=ACCOUNT-NAME]**. uid1 name. Defaults to admin [default: "admin"]
- ** --account-mail[=ACCOUNT-MAIL]**. uid1 email. Defaults to admin@example.com [default: "admin@example.com"]
- ** --site-mail[=SITE-MAIL]**. From: for system mailings. Defaults to admin@example.com [default: "admin@example.com"]
- ** --account-pass=ACCOUNT-PASS**. uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in config.yml.
- ** --locale[=LOCALE]**. A short language code. Sets the default site language. Language files must already be present. [default: "en"]
- ** --site-name[=SITE-NAME]**. Defaults to Site-Install [default: "Drush Site-Install"]
- ** --site-pass=SITE-PASS**. 
- ** --sites-subdir=SITES-SUBDIR**. Name of directory under 'sites' which should be created.
- ** --config-dir=CONFIG-DIR**. Deprecated - only use with Drupal 8.5-. A path pointing to a full set of configuration which should be installed during installation.
- ** --existing-config**. Configuration from "sync" directory should be imported during installation. Use with Drupal 8.6+.

#### Aliases

- si
- sin
- site-install

