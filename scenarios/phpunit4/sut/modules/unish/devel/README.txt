Devel
==========
Devel module contains helper functions and pages for Drupal developers and inquisitive admins:

 - A block for running custom PHP on a page
 - A block for quickly accessing devel pages
 - A block for masquerading as other users (useful for testing)
 - A mail-system class which redirects outbound email to files
 - Drush commands such as fn-hook, fn-event, ...
 - Docs at https://api.drupal.org/api/devel
 - more

This module is safe to use on a production site. Just be sure to only grant
'access development information' permission to developers.

Devel Kint
===================
Provides a dpr() function, which pretty prints variables.
Useful during development. Also see similar helpers like dpm(), dvm().

Webprofiler
==============
Adds a debug bar at bottom of all pages with tons of useful information like a query list,
cache hit/miss data, memory profiling, page speed, php info, session info, etc.

Devel Generate
=================
Bulk creates nodes, users, comment, terms for development. Has Drush integration.

Devel Generate Extensions
=========================
Devel Images Provider [http://drupal.org/project/devel_image_provider] allows to configure external providers for images.

Drush Unit Testing
==================
See develDrushTest.php for an example of unit testing of the Drush integration.
This uses Drush's own test framework, based on PHPUnit. To run the tests, use
run-tests-drush.sh. You may pass in any arguments that are valid for `phpunit`.

Author/Maintainers
======================
- Moshe Weitzman <weitzman at tejasa DOT com> http://www.acquia.com
- Hans Salvisberg <drupal at salvisberg DOT com>
- Pedro Cambra https://drupal.org/user/122101/contact http://www.ymbra.com/
- Juan Pablo Novillo https://www.drupal.org/u/juampynr
- lussoluca https://www.drupal.org/u/lussoluca
- willzyx https://www.drupal.org/u/willzyx
