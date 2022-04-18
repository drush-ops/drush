---
edit_url: https://github.com/drush-ops/drush/blob/11.x/CONTRIBUTING.md
---
Drush is built by people like you! Please [join us](https://github.com/drush-ops/drush).

## Git and Pull requests
* Contributions are submitted, reviewed, and accepted using GitHub pull requests.
* The latest changes are in the `11.x` branch. PR's should initially target this branch.
* See the test-specific [README.md](https://github.com/drush-ops/drush/blob/11.x/docs/contribute/unish.md) for instructions on running the test suite. Test before you push. Get familiar with Unish, our test suite. Optionally run tests in the provided Docker containers.
* We maintain branches named 11.x, 10.x, etc. These are release branches. From these branches, we make new tags for patch and minor versions.

## Development Environment
* You may choose to use the [DDEV](https://ddev.readthedocs.io/) for a [standardized development environment](/.ddev/config.yaml).
* See `composer run-script -l` for a list of helper scripts.

## Coding style
* Do write comments. You don't have to comment every line, but if you come up with something that's a bit complex/weird, just leave a comment. Bear in mind that you will probably leave the project at some point and that other people will read your code. Undocumented huge amounts of code are nearly worthless!
* We use [PSR-12](https://www.php-fig.org/psr/psr-12/).
* Keep it compatible. Do not introduce changes to the public API, or configurations too casually. Don't make incompatible changes without good reasons!
* Run `composer cs` to check the project for coding style issues and run `composer cbf` to fix them automatically where possible. These scripts use [`PHP_CodeSniffer`](https://github.com/squizlabs/PHP_CodeSniffer) in background.

## Documentation
* The docs are on our [web site](https://www.drush.org). You may also read these from within Drush, with the `drush topic` command.
* Documentation should be kept up-to-date. This means, whenever you add a new API method, add a new hook or change the database model, pack the relevant changes to the docs in the same pull request.
