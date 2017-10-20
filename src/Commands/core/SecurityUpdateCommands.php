<?php
namespace Drush\Commands\core;

use Composer\Semver\Comparator;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Exception;
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
     * @command pm:security
     * @aliases sec
     * @bootstrap none
     * @table-style default
     * @field-labels
     *   name: Name
     *   version: Installed Version
     *   min-version: Suggested version
     * @default-fields name,version,min-version
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *
     * @throws \Exception
     */
    public function securityUpdates()
    {
        $security_updates = [];
        try {
            $response_body = file_get_contents('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x/composer.json');
        }
        catch (\Exception $e) {
            throw new Exception("Unable to fetch drupal-security-advisories information.");

        }
        $data = json_decode($response_body, TRUE);
        $composer_root = Drush::bootstrapManager()->getComposerRoot();
        $composer_lock_file_path = Path::join($composer_root, 'composer.lock');
        if (!file_exists($composer_lock_file_path)) {
            throw new \Exception("Cannot find composer.lock file.");
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
                                'name' => $name,
                                'version' => $package['version'],
                                'min-version' => $min_version,
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
            $suggested_command = 'composer require ';
            foreach ($security_updates as $package) {
                $suggested_command .= $package['name'] . ':^' . $package['min-version'] . ' ';
            }
            $suggested_command .= '--update-with-dependencies';
            $this->output()->writeln("<error>One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.</error>");
            $this->output()->writeln("Try running: <comment>$suggested_command</comment>");
            $this->output()->writeln("If that fails due a conflict then you must update one or more root dependencies.");

            $result = new RowsOfFields($security_updates);

            return $result;
        }
        else {
            $this->output()->writeln("<info>There are no outstanding security updates for Drupal projects.</info>");
        }
    }
}
