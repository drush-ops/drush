Drush is built by people like you! Please [join us](https://github.com/drush-ops/drush).

## Git and Pull requests
* Contributions are submitted, reviewed, and accepted using Github pull requests. [Read this article](https://help.github.com/articles/using-pull-requests) for some details. We use the _Fork and Pull_ model, as described there.
* The latest changes are in the `master` branch. PR's should initially target this branch.
* Try to make clean commits that are easily readable (including descriptive commit messages!)
* Test before you push. Get familiar with Unish, our test suite. See the test-specific [README.md](tests/README.md)
* We maintain branches named 9.x, 8.x, etc. These are release branches. From these branches, we make new tags for patch and minor versions.

## Coding style
* Do write comments. You don't have to comment every line, but if you come up with something thats a bit complex/weird, just leave a comment. Bear in mind that you will probably leave the project at some point and that other people will read your code. Undocumented huge amounts of code are nearly worthless!
* We use [PSR-2](http://www.php-fig.org/psr/psr-2/) in the /src directory. [Drupal's coding standards](https://drupal.org/coding-standards) are still used in the includes directory (deprecated code).
* Don't overengineer. Don't try to solve any possible problem in one step, but try to solve problems as easy as possible and improve the solution over time!
* Do generalize sooner or later! (if an old solution, quickly hacked together, poses more problems than it solves today, refactor it!)
* Keep it compatible. Do not introduce changes to the public API, or configurations too casually. Don't make incompatible changes without good reasons!

## Documentation
* The docs are in the [docs](docs) and [examples](examples) folders in the git repository, so people can easily find the suitable docs for the current git revision. You can read these from within Drush, with the `drush topic` command.
* Documentation should be kept up-to-date. This means, whenever you add a new API method, add a new hook or change the database model, pack the relevant changes to the docs in the same pull request.
