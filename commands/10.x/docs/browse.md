# browse

Display a link to a given path or open link in a browser.

#### Examples

- <code>drush browse</code>. Open default web browser (if configured or detected) to the site front page.
- <code>drush browse node/1</code>. Open web browser to the path node/1.
- <code>drush @example.prod</code>. Open a browser to the web site specified in a site alias.
- <code>drush browse --browser=firefox admin</code>. Open Firefox web browser to the path 'admin'.

#### Arguments

- **path**. Path to open. If omitted, the site front page will be opened.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --browser=BROWSER**. Specify a particular browser (defaults to operating system default). Use --no-browser to suppress opening a browser.
- ** --redirect-port=REDIRECT-PORT**. The port that the web server is redirected to (e.g. when running within a Vagrant environment).

