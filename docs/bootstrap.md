The Drush Bootstrap Process
===========================
When preparing to run a command, Drush works by "bootstrapping" the Drupal environment in very much the same way that is done during a normal page request from the web server, so most Drush commands run in the context of a fully-initialized website.

For efficiency and convenience, some Drush commands can work without first bootstrapping a Drupal site, or by only partially bootstrapping a site. This is faster than a full bootstrap. It is also a matter of convenience, because some commands are useful even when you don't have a working Drupal site. For example, you can use Drush to download Drupal with `drush dl drupal`. This obviously does not require any bootstrapping to work.

DRUSH\_BOOTSTRAP\_NONE
-----------------------
Only run Drush _preflight_, without considering Drupal at all. Any code that operates on the Drush installation, and not specifically any Drupal directory, should bootstrap to this phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_ROOT
------------------------------
Set up and test for a valid Drupal root, either through the --root options, or evaluated based on the current working directory. Any code that interacts with an entire Drupal installation, and not a specific site on the Drupal installation should use this bootstrap phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_SITE
------------------------------
Set up a Drupal site directory and the correct environment variables to allow Drupal to find the configuration file. If no site is specified with the --uri options, Drush will assume the site is 'default', which mimics Drupal's behaviour.  Note that it is necessary to specify a full URI, e.g. --uri=http://example.com, in order for certain Drush commands and Drupal modules to behave correctly. See the [example Config file](../examples/example.drushrc.php) for more information. Any code that needs to modify or interact with a specific Drupal site's settings.php file should bootstrap to this phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_CONFIGURATION
---------------------------------------
Load the settings from the Drupal sites directory. This phase is analagous to the DRUPAL\_BOOTSTRAP\_CONFIGURATION bootstrap phase in Drupal itself, and this is also the first step where Drupal specific code is included. This phase is commonly used for code that interacts with the Drupal install API, as both install.php and update.php start at this phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_DATABASE
----------------------------------
Connect to the Drupal database using the database credentials loaded during the previous bootstrap phase. This phase is analogous to the DRUPAL\_BOOTSTRAP\_DATABASE bootstrap phase in Drupal. Any code that needs to interact with the Drupal database API needs to be bootstrapped to at least this phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_FULL
------------------------------
Fully initialize Drupal. This is analogous to the DRUPAL\_BOOTSTRAP\_FULL bootstrap phase in Drupal. Any code that interacts with the general Drupal API should be bootstrapped to this phase.

DRUSH\_BOOTSTRAP\_DRUPAL\_LOGIN
-------------------------------
Log in to the initialiazed Drupal site. This bootstrap phase is used after the site has been fully bootstrapped. This is the default bootstrap phase all commands will try to reach, unless otherwise specified. This phase will log you in to the drupal site with the username or user ID specified by the --user/ -u option(defaults to 0, anonymous). Use this bootstrap phase for your command if you need to have access to information for a specific user, such as listing nodes that might be different based on who is logged in.

DRUSH\_BOOTSTRAP\_MAX
---------------------
This is not an actual bootstrap phase. Commands that use DRUSH\_BOOTSTRAP\_MAX will cause Drush to bootstrap as far as possible, and then run the command regardless of the bootstrap phase that was reached. This is useful for Drush commands that work without a bootstrapped site, but that provide additional information or capabilities in the presence of a bootstrapped site. For example, `drush pm-releases modulename` works without a bootstrapped Drupal site, but will include the version number for the installed module if a Drupal site has been bootstrapped.

