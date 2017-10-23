<?php
namespace Drush\Commands\pm;

use Composer\Semver\Comparator;
use Consolidation\AnnotatedCommand\CommandData;
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
     * @var array
     */
    protected $securityUpdates;

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
    public function security()
    {
        $this->securityUpdates = [];
        try {
            $response_body = file_get_contents('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x/composer.json');
        } catch (Exception $e) {
            throw new Exception("Unable to fetch drupal-security-advisories information.");
        }
        $security_advisories_composer_json = json_decode($response_body, true);
        $composer_root = Drush::bootstrapManager()->getComposerRoot();
        $composer_lock_file_name = getenv('COMPOSER') ? str_replace('.json', '', getenv('COMPOSER')) : 'composer';
        $composer_lock_file_name .= '.lock';
        $composer_lock_file_path = Path::join($composer_root, $composer_lock_file_name);
        if (!file_exists($composer_lock_file_path)) {
            throw new Exception("Cannot find $composer_lock_file_path!");
        }
        $composer_lock_contents = file_get_contents($composer_lock_file_path);
        $composer_lock_data = json_decode($composer_lock_contents, true);
        if (!array_key_exists('packages', $composer_lock_data)) {
            throw new Exception("No packages were found in $composer_lock_file_path! Contents:\n $composer_lock_contents");
        }
        foreach ($composer_lock_data['packages'] as $key => $package) {
            $name = $package['name'];
            if (!empty($security_advisories_composer_json['conflict'][$name])) {
                $conflict_constraints = explode(',', $security_advisories_composer_json['conflict'][$name]);
                foreach ($conflict_constraints as $conflict_constraint) {
                    // Only parse constraints that follow pattern like "<1.0.0".
                    if (substr($conflict_constraint, 0, 1) == '<') {
                        $min_version = substr($conflict_constraint, 1);
                        if (Comparator::lessThan($package['version'], $min_version)) {
                            $this->securityUpdates[$name] = [
                                'name' => $name,
                                'version' => $package['version'],
                                'min-version' => $min_version,
                            ];
                        }
                    } else {
                        $this->logger()->warning("Could not parse drupal-security-advisories conflicting version constraint $conflict_constraint for package $name.");
                    }
                }
            }
        }
        if ($this->securityUpdates) {
            // @todo Modernize.
            drush_set_context('DRUSH_EXIT_CODE', DRUSH_FRAMEWORK_ERROR);
            $result = new RowsOfFields($this->securityUpdates);
            return $result;
        } else {
            $this->logger()->notice("<info>There are no outstanding security updates for Drupal projects.</info>");
        }
    }

    /**
     * Emit suggested composer command for security updates.
     *
     * @hook post-command pm:security
     */
    public function suggestComposerCommand($result, CommandData $commandData)
    {
        if (!empty($this->securityUpdates)) {
            $suggested_command = 'composer require ';
            foreach ($this->securityUpdates as $package) {
                $suggested_command .= $package['name'] . ':^' . $package['min-version'] . ' ';
            }
            $suggested_command .= '--update-with-dependencies';
            $this->logger()->warning("One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.");
            $this->logger()->notice("Try running: <comment>$suggested_command</comment>");
            $this->logger()->notice("If that fails due to a conflict then you must update one or more root dependencies.");
        }
    }
}
