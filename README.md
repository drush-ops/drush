---
edit_url: https://github.com/drush-ops/drush/blob/10.x/README.md
---
Drush is a command line shell and Unix scripting interface for Drupal. Drush core ships with lots of [useful commands](commands/10.x/all.md) for interacting with code like modules/themes/profiles. Similarly, it runs update.php, executes SQL queries and DB migrations, and misc utilities like run cron or clear cache. Developers love the [generate command](commands/10.x/generate.md), which jump starts your coding project by writing ready-to-customize PHP and YML files. Drush can be extended by [3rd party commandfiles](https://www.drupal.org/project/project_module?f[2]=im_vid_3%3A4654).

[![Latest Stable Version](https://poser.pugx.org/drush/drush/v/stable.png)](https://packagist.org/packages/drush/drush) [![Total Downloads](https://poser.pugx.org/drush/drush/downloads.png)](https://packagist.org/packages/drush/drush) [![License](https://poser.pugx.org/drush/drush/license.png)](https://packagist.org/packages/drush/drush) <a href="https://circleci.com/gh/drush-ops/drush"><img src="https://circleci.com/gh/drush-ops/drush.svg?style=shield"></a> [![Twitter](https://img.shields.io/badge/Twitter-%40DrushCli-blue.svg)](https://twitter.com/intent/user?screen_name=DrushCli)

Resources
-----------
* [Installing and Upgrading](install.md) ([Drush 8](https://docs.drush.org/en/8.x/install/))
* [General Documentation](usage.md) ([Drush 8](https://docs.drush.org/en/8.x/install/))
* [Drush Commands](commands/10.x/all.md)
* [API Documentation](/api/10.x/index.html)
* [Drush packages available via Composer](https://packagist.org/search/?type=drupal-drush)
* [A list of modules that include Drush integration](https://www.drupal.org/project/project_module?f[2]=im_vid_3%3A4654&solrsort=ds_project_latest_release+desc)
* Drush comes with a [full test suite](https://github.com/drush-ops/drush/blob/10.x/tests/README.md) powered by [PHPUnit](https://github.com/sebastianbergmann/phpunit). Each commit gets tested by our CI bots.

Support
-----------
* Post support requests to [Drupal Answers](http://drupal.stackexchange.com/questions/tagged/drush). Tag question with 'drush'.
* Report bugs and request features in the [GitHub Drush Issue Queue](https://github.com/drush-ops/drush/issues).
* Use pull requests (PRs) to contribute to Drush.

Code of Conduct
---------------
The Drush project expects all participants to abide by the [Drupal Code of Conduct](https://www.drupal.org/dcoc).

FAQ
------

#### What does *Drush* stand for?
A: The Drupal Shell.

#### How do I pronounce Drush?
Some people pronounce the *dru* with a long 'u' like Dr*u*pal. Fidelity points
go to them, but they are in the minority. Most pronounce Drush so that it
rhymes with hush, rush, flush, etc. This is the preferred pronunciation.

#### Does Drush have unit tests?
Drush has an excellent suite of unit tests. See [tests/README.md](https://github.com/drush-ops/drush/blob/10.x/tests/README.md) for more information.


Credits
-----------

* Maintained by [Moshe Weitzman](http://drupal.org/moshe) with much help from the folks listed at https://github.com/orgs/drush-ops/people.
* Originally developed by [Arto Bendiken](http://bendiken.net) for Drupal 4.7.
* Redesigned by [Franz Heinzmann](http://unbiskant.org) in May 2007 for Drupal 5.
* Thanks to [JetBrains](https://www.jetbrains.com) for [supporting this project and open source software](https://www.jetbrains.com/buy/opensource/).

![Drush Logo](drush_logo-black.png)
[![PhpStorm Logo](misc/icon_PhpStorm.png)](https://www.jetbrains.com/phpstorm/)
