DESCRIPTION
-----------

Drush is a command line shell and Unix scripting interface for Drupal. Drush core ships with lots of useful commands for interacting with code like modules/themes/profiles. Similarly, it runs update.php, executes sql queries and DB migrations, and misc utilities like run cron or clear cache. Drush can be extended by [3rd party commandfiles](https://www.drupal.org/project/project_module?f[2]=im_vid_3%3A4654).

[![Latest Stable Version](https://poser.pugx.org/drush/drush/v/stable.png)](https://packagist.org/packages/drush/drush) [![Total Downloads](https://poser.pugx.org/drush/drush/downloads.png)](https://packagist.org/packages/drush/drush) [![Latest Unstable Version](https://poser.pugx.org/drush/drush/v/unstable.png)](https://packagist.org/packages/drush/drush) [![License](https://poser.pugx.org/drush/drush/license.png)](https://packagist.org/packages/drush/drush)

DRUSH VERSIONS
--------------

Each version of Drush supports multiple Drupal versions.  Drush 6 is recommended version.

Drush Version | Branch  | PHP | Compatible Drupal versions | Code Status
------------- | ------  | --- | -------------------------- | -----------
Drush 7       | [master](https://travis-ci.org/drush-ops/drush)  | 5.3.0+ | D6, D7, D8                 | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=master">
Drush 6       | [6.x](https://travis-ci.org/drush-ops/drush) | 5.3.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.svg?branch=6.x">
Drush 5       | [5.x](https://travis-ci.org/drush-ops/drush) | 5.2.0+ | D6, D7                     | Unsupported
Drush 4       | 4.x | 5.2.0+ | D5, D6, D7                 | Unsupported
Drush 3       | 3.x | 5.2.0+ | D5, D6                     | Unsupported

Drush comes with a full test suite powered by [PHPUnit](https://github.com/sebastianbergmann/phpunit). Each commit gets tested by the awesome [Travis.ci continuous integration service](https://travis-ci.org/drush-ops/drush).

MISC
-----------
* [API Documentation](http://api.drush.org)
* [Drush Commands](http://drushcommands.com)
* Subscribe to https://github.com/drush-ops/drush/releases.atom to receive notification on new releases.
* [A list of modules that include Drush integration](http://drupal.org/project/modules?filters=tid%3A4654)
* If you are using Debian or Ubuntu, you can alternatively use the Debian packages uploaded in your distribution. You may need to use the backports to get the latest version, if you are running a LTS or "stable" release.

SUPPORT
-----------

Please take a moment to review the rest of the information in this file before
pursuing one of the support options below.

* Post support requests to [Drupal Answers](http://drupal.stackexchange.com/questions/tagged/drush).
* Bug reports and feature requests should be reported in the [GitHub Drush Issue Queue](https://github.com/drush-ops/drush/issues).
* Use pull requests (PRs) to contribute to Drush.
* It is still possible to search the old issue queue on Drupal.org for [fixed bugs](https://drupal.org/project/issues/search/drush?status%5B%5D=7&categories%5B%5D=bug), [unmigrated issues](https://drupal.org/project/issues/search/drush?status%5B%5D=5&issue_tags=needs+migration), [unmigrated bugs](https://drupal.org/project/issues/search/drush?status%5B%5D=5&categories%5B%5D=bug&issue_tags=needs+migration), and so on.

REQUIREMENTS
-----------

* Drush commands that work with git require git 1.7 or greater.
* Drush works best on a Unix-like OS (Linux, OS X)
* Most Drush commands run on Windows.  See INSTALLING DRUSH ON WINDOWS, below.

FAQ
------

```
  Q: What does "drush" stand for?
  A: The Drupal Shell.

  Q: How do I pronounce Drush?
  A: Some people pronounce the dru with a long u like Drupal. Fidelity points
     go to them, but they are in the minority. Most pronounce Drush so that it
     rhymes with hush, rush, flush, etc. This is the preferred pronunciation.

  Q: Does Drush have unit tests?
  A: Drush has an excellent suite of unit tests. See the README.md file in the /tests subdirectory for
     more information.
```

CREDITS
-----------

* Originally developed by [Arto Bendiken](http://bendiken.net) for Drupal 4.7.
* Redesigned by [Franz Heinzmann](http://unbiskant.org) in May 2007 for Drupal 5.
* Maintained by [Moshe Weitzman](http://drupal.org/moshe) with much help from
  Owen Barton, greg.1.anderson, jonhattan, Mark Sonnabaum, and Jonathan Hedstrom.

![Drush Logo](drush_logo-black.png)
