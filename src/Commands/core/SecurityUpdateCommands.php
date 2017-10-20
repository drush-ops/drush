<?php
namespace Drush\Commands\core;

use Composer\Semver\Comparator;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use GuzzleHttp\Client;
use Symfony\Component\Console\Helper\Table;
use Webmozart\PathUtil\Path;

/**
 * Check Drupal Composer packages for security updates.
 */
class SecurityUpdateCommands extends DrushCommands
{
    /**
     * Check Drupal Composer packages for security updates.
     *
     * This uses the Drupal security advisories package to determine if updates
     * are available.
     *
     * @see https://github.com/drupal-composer/drupal-security-advisories
     *
     * @command security-updates
     * @aliases sups
     * @bootstrap none
     */
    public function securityUpdates()
    {
        $security_updates = [];
        $client = new Client();
        $response = $client->get('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x/composer.json');
        if ($response->getStatusCode() != 200) {
            $this->logger()->error("Received {$response->getStatusCode()} when attempting to fetch drupal-security-advisories information.");
            return 1;
        }
        $response_body = $response->getBody();
        $data = json_decode($response_body, TRUE);
        $composer_root = Drush::bootstrapManager()->getComposerRoot();
        $composer_lock_file_path = Path::join($composer_root, 'composer.lock');
        if (!file_exists($composer_lock_file_path)) {
            $this->logger()->error("Cannot find composer.lock file.");
            return 1;
        }
        $composer_lock_contents = json_decode(file_get_contents($composer_lock_file_path), TRUE);
        foreach ($composer_lock_contents['packages'] as $key => $package) {
            $name = $package['name'];
            if (!empty($data['conflict'][$name])) {
                $conflict_constraints = explode(',', $data['conflict'][$name]);
                foreach ($conflict_constraints as $conflict_constraint) {
                    if (substr($conflict_constraint, 0, 1) == '<') {
                        $min_version = substr($conflict_constraint, 1);
                        if (Comparator::lessThan($package['version'], $min_version)) {
                            $security_updates[$name] = [
                                $name,
                                $package['version'],
                                $min_version,
                            ];
                        }
                    }
                    else {
                        $this->logger()->warning("Could not parse conflicting version constraint $conflict_constraint for package $name.");
                    }
                }
            }
        }
        if ($security_updates) {
            $this->logger()->error("One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.");
            $table = new Table($this->output);
            $table->setHeaders(['Name', 'Installed version', 'Suggested version'])
                ->setRows($security_updates)
                ->render();
            return 1;
        }
        else {
            $this->output()->writeln("<info>There are no outstanding security updates for Drupal projects.</info>");
            return 0;
        }
    }
}
