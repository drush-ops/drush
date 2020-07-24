# user:login

Display a one time login link for user ID 1, or another user.

#### Examples

- <code>drush user:login</code>. Open default web browser and browse to homepage, logged in as uid=1.
- <code>drush user:login --name=ryan node/add/blog</code>. Open default web browser (if configured or detected) for a one-time login link for username ryan that redirects to node/add/blog.
- <code>drush user:login --uid=123</code>. Open default web browser and login as user with uid "123".
- <code>drush user:login --mail=foo@bar.com</code>. Open default web browser and login as user with mail "foo@bar.com".
- <code>drush user:login --browser=firefox --name=$(drush user:information --mail="drush@example.org" --fields=name --format=string)</code>. Open firefox web browser, and login as the user with the e-mail address drush@example.org.

#### Arguments

- **path**. Optional path to redirect to after logging in.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --name[=NAME]**. A user name to log in as.
- ** --uid[=UID]**. A uid to log in as.
- ** --mail[=MAIL]**. A user mail address to log in as.
- ** --browser[=BROWSER]**. Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser. [default: "1"]
- ** --redirect-port=REDIRECT-PORT**. A custom port for redirecting to (e.g., when running within a Vagrant environment)
- ** --no-browser**. Negate --browser option.

#### Aliases

- uli
- user-login

