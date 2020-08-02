---
edit_url: https://github.com/drush-ops/drush/blob/10.x/CONTRIBUTING.md
---
Drush is built by people like you! Please [join us](https://github.com/drush-ops/drush).

## Git and Pull requests
* Contributions are submitted, reviewed, and accepted using GitHub pull requests.
* The latest changes are in the `10.x` branch. PR's should initially target this branch.
* Try to make clean commits that are easily readable (including descriptive commit messages!)
* See the test-specific [README.md](https://github.com/drush-ops/drush/blob/10.x/tests/README.md) for instructions on running the test suite. Test before you push. Get familiar with Unish, our test suite. Optionally run tests in the provided Docker containers.
* We maintain branches named 10.x, 9.x, etc. These are release branches. From these branches, we make new tags for patch and minor versions.

## Development Environment
* You may choose to use the docker-compose file in root directory for an optimized environment.
* See `composer run-script -l` for a list of helper scripts.

## Coding style
* Do write comments. You don't have to comment every line, but if you come up with something that'sZ a bit complex/weird, just leave a comment. Bear in mind that you will probably leave the project at some point and that other people will read your code. Undocumented huge amounts of code are nearly worthless!
* We use [PSR-2](https://www.php-fig.org/psr/psr-2/) in the /src directory. [Drupal's coding standards](https://drupal.org/coding-standards) are still used in the includes directory (deprecated code).
* Keep it compatible. Do not introduce changes to the public API, or configurations too casually. Don't make incompatible changes without good reasons!

## Documentation
* The docs are on our [web site](https://www.drush.org). You may also read these from within Drush, with the `drush topic` command.
* Documentation should be kept up-to-date. This means, whenever you add a new API method, add a new hook or change the database model, pack the relevant changes to the docs in the same pull request.
