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
     * Check Drupal Composer packages for pending security updates.
     *
     * This uses the Drupal security advisories package to determine if updates
     * are available.
     *
     * @see https://github.com/drupal-composer/drupal-security-advisories
     *
     * @command pm:security
     * @aliases sec,pm-security
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
        $security_advisories_composer_json = $this->fetchAdvisoryComposerJson();
        $composer_lock_data = $this->loadSiteComposerLock();
        $this->registerAllSecurityUpdates($composer_lock_data, $security_advisories_composer_json);
        if ($this->securityUpdates) {
            // @todo Modernize.
            drush_set_context('DRUSH_EXIT_CODE', DRUSH_FRAMEWORK_ERROR);
            $result = new RowsOfFields($this->securityUpdates);
            return $result;
        } else {
            $this->logger()->success("<info>There are no outstanding security updates for Drupal projects.</info>");
        }
    }

    /**
     * Emit suggested Composer command for security updates.
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

    /**
     * Fetches the generated composer.json from drupal-security-advisories.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function fetchAdvisoryComposerJson()
    {
        try {
            $response_body = file_get_contents('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x/composer.json');
        } catch (Exception $e) {
            throw new Exception("Unable to fetch drupal-security-advisories information.");
        }
        $security_advisories_composer_json = json_decode($response_body, true);
        return $security_advisories_composer_json;
    }

    /**
     * Loads the contents of the local Drupal application's composer.lock file.
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function loadSiteComposerLock()
    {
        $composer_root = Drush::bootstrapManager()->getComposerRoot();
        $composer_lock_file_name = getenv('COMPOSER') ? str_replace(
            '.json',
            '',
            getenv('COMPOSER')
        ) : 'composer';
        $composer_lock_file_name .= '.lock';
        $composer_lock_file_path = Path::join(
            $composer_root,
            $composer_lock_file_name
        );
        if (!file_exists($composer_lock_file_path)) {
            throw new Exception("Cannot find $composer_lock_file_path!");
        }
        $composer_lock_contents = file_get_contents($composer_lock_file_path);
        $composer_lock_data = json_decode($composer_lock_contents, true);
        if (!array_key_exists('packages', $composer_lock_data)) {
            throw new Exception("No packages were found in $composer_lock_file_path! Contents:\n $composer_lock_contents");
        }
        return $composer_lock_data;
    }

    /**
     * Register all available security updates in $this->securityUpdates.
     * @param array $composer_lock_data
     *   The contents of the local Drupal application's composer.lock file.
     * @param array $security_advisories_composer_json
     *   The composer.json array from drupal-security-advisories.
     */
    protected function registerAllSecurityUpdates(
        $composer_lock_data,
        $security_advisories_composer_json
    ) {
        foreach ($composer_lock_data['packages'] as $key => $package) {
            $name = $package['name'];
            $this->registerPackageSecurityUpdates($security_advisories_composer_json, $name, $package);
        }
    }

    /**
     * Determines if update is avaiable based on a conflict constraint.
     *
     * @param string $conflict_constraint
     *   The constraint for the conflicting, insecure package version.
     *   E.g., <1.0.0.
     * @param array $package
     *   The package to be evaluated.
     * @param string $name
     *   The human readable display name for the package.
     *
     * @return array
     *   An associative array containing name, version, and min-version keys.
     */
    public static function determineUpdatesFromConstraint(
        $conflict_constraint,
        $package,
        $name
    ) {
        // Only parse constraints that follow pattern like "<1.0.0".
        if (substr($conflict_constraint, 0, 1) == '<') {
            $min_version = substr($conflict_constraint, 1);
            if (Comparator::lessThan(
                $package['version'],
                $min_version
            )) {
                return [
                    'name' => $name,
                    'version' => $package['version'],
                    // Assume that conflict constraint of <1.0.0 indicates that
                    // 1.0.0 is the available, secure version.
                    'min-version' => $min_version,
                ];
            }
        } // Compare exact versions that are insecure.
        elseif (preg_match(
            '/^[[:digit:]](?![-*><=~ ])/',
            $conflict_constraint
        )) {
            $exact_version = $conflict_constraint;
            if (Comparator::equalTo(
                $package['version'],
                $exact_version
            )) {
                $version_parts = explode('.', $package['version']);
                if (count($version_parts) == 3) {
                    $version_parts[2]++;
                    $min_version = implode('.', $version_parts);
                    return [
                        'name' => $name,
                        'version' => $package['version'],
                        // Assume that conflict constraint of 1.0.0 indicates that
                        // 1.0.1 is the available, secure version.
                        'min-version' => $min_version,
                    ];
                }
            }
        }
        return [];
    }

    /**
     * Registers available security updates for a given package.
     *
     * @param array $security_advisories_composer_json
     *   The composer.json array from drupal-security-advisories.
     * @param string $name
     *   The human readable display name for the package.
     * @param array $package
     *   The package to be evaluated.
     */
    protected function registerPackageSecurityUpdates(
        $security_advisories_composer_json,
        $name,
        $package
    ) {
        if (empty($this->securityUpdates[$name]) &&
            !empty($security_advisories_composer_json['conflict'][$name])) {
            $conflict_constraints = explode(
                ',',
                $security_advisories_composer_json['conflict'][$name]
            );
            foreach ($conflict_constraints as $conflict_constraint) {
                $available_update = $this->determineUpdatesFromConstraint(
                    $conflict_constraint,
                    $package,
                    $name
                );
                if ($available_update) {
                    $this->securityUpdates[$name] = $available_update;
                }
            }
        }
    }
}
