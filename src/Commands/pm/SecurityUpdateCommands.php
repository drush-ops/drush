<?php
namespace Drush\Commands\pm;

use Composer\Semver\Semver;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
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
     * Return path to composer.lock
     *
     * @return string
     * @throws \Exception
     */
    public static function composerLockPath(): string
    {
        $composer_root = Drush::bootstrapManager()->getComposerRoot();
        $composer_lock_file_name = getenv('COMPOSER') ? str_replace('.json', '', getenv('COMPOSER')) : 'composer';
        $composer_lock_file_name .= '.lock';
        $composer_lock_file_path = Path::join($composer_root, $composer_lock_file_name);
        if (!file_exists($composer_lock_file_path)) {
            throw new Exception("Cannot find $composer_lock_file_path!");
        }
        return $composer_lock_file_path;
    }

    /**
     * Check Drupal Composer packages for pending security updates.
     *
     * This uses the [Drupal security advisories package](https://github.com/drupal-composer/drupal-security-advisories) to determine if updates
     * are available.
     *
     * @command pm:security
     * @aliases sec,pm-security
     * @bootstrap none
     * @table-style default
     * @field-labels
     *   name: Name
     *   version: Installed Version
     * @default-fields name,version
     *
     * @filter-default-field name
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *
     * @throws \Exception
     */
    public function security()
    {
        $security_advisories_composer_json = $this->fetchAdvisoryComposerJson();
        $composer_lock_data = $this->loadSiteComposerLock();
        $updates = $this->calculateSecurityUpdates($composer_lock_data, $security_advisories_composer_json);
        if ($updates) {
            $this->suggestComposerCommand($updates);
            return CommandResult::dataWithExitCode(new RowsOfFields($updates), self::EXIT_FAILURE);
        } else {
            $this->logger()->success("<info>There are no outstanding security updates for Drupal projects.</info>");
        }
    }

    /**
     * Emit suggested Composer command for security updates.
     */
    public function suggestComposerCommand($updates)
    {
        $suggested_command = 'composer require ';
        foreach ($updates as $package) {
            $suggested_command .= $package['name'] . ' ';
        }
        $suggested_command .= '--update-with-dependencies';
        $this->logger()->warning('One or more of your dependencies has an outstanding security update.');
        $this->logger()->notice("Try running: <comment>$suggested_command</comment>");
        $this->logger()->notice("If that fails due to a conflict then you must update one or more root dependencies.");
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
            // We use the v2 branch for now, as per https://github.com/drupal-composer/drupal-security-advisories/pull/11.
            $response_body = file_get_contents('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x-v2/composer.json');
            if ($response_body === false) {
                throw new Exception("Unable to fetch drupal-security-advisories information.");
            }
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
        $composer_lock_file_path = self::composerLockPath();
        $composer_lock_contents = file_get_contents($composer_lock_file_path);
        $composer_lock_data = json_decode($composer_lock_contents, true);
        if (!array_key_exists('packages', $composer_lock_data)) {
            throw new Exception("No packages were found in $composer_lock_file_path! Contents:\n $composer_lock_contents");
        }
        return $composer_lock_data;
    }

    /**
     * Return available security updates.
     *
     * @param array $composer_lock_data
     *   The contents of the local Drupal application's composer.lock file.
     * @param array $security_advisories_composer_json
     *   The composer.json array from drupal-security-advisories.
     *
     * @return array
     */
    protected function calculateSecurityUpdates($composer_lock_data, $security_advisories_composer_json)
    {
        $updates = [];
        $both = array_merge($composer_lock_data['packages-dev'], $composer_lock_data['packages']);
        $conflict = $security_advisories_composer_json['conflict'];
        foreach ($both as $package) {
            $name = $package['name'];
            if (!empty($conflict[$name]) && Semver::satisfies($package['version'], $security_advisories_composer_json['conflict'][$name])) {
                $updates[$name] = [
                    'name' => $name,
                    'version' => $package['version'],
                ];
            }
        }
        return $updates;
    }

    /**
     * Check non-Drupal PHP packages for pending security updates.
     *
     * Thanks to https://github.com/FriendsOfPHP/security-advisories and Symfony
     * for providing this service.
     *
     * @param array $options
     *
     * @return UnstructuredData
     * @throws \Exception
     * @command pm:security-php
     * @aliases sec-php,pm-security-php
     * @bootstrap none
     *
     * @usage drush pm:security-php --format=json
     *   Get security data in JSON format.
     */
    public function securityPhp($options = ['format' => 'yaml'])
    {
        $path = self::composerLockPath();
        // Note: wget does not support multipart/form-data so its not supported by this command.
        $command = ['curl', '-H', 'Accept: application/json', 'https://security.symfony.com/check_lock', '-F', "lock=@{$path}"];
        $process = $this->processManager()->process($command);
        $process->mustRun();
        $out = $process->getOutput();
        if ($packages = json_decode($out, true)) {
            $suggested_command = "composer why " . implode(' && composer why ', array_keys($packages));
            $this->logger()->warning('One or more of your dependencies has an outstanding security update.');
            $this->logger()->notice("Run <comment>$suggested_command</comment> to learn what module requires the package.");
            return CommandResult::dataWithExitCode(new UnstructuredData($packages), self::EXIT_FAILURE);
        }
        $this->logger()->success("There are no outstanding security updates for your dependencies.");
    }
}
