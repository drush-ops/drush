<?php

namespace Drush\Commands\pm;

use GuzzleHttp\Client;
use Composer\Semver\Semver;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Enlightn\SecurityChecker\SecurityChecker;
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
     * are available. An exit code of 3 indicates that the check completed, and insecure packages were found.
     *
     * @command pm:security
     * @aliases sec,pm-security
     * @option no-dev Only check production dependencies.
     * @usage drush pm:security --format=json
     *   Get security data in JSON format.
     * @usage HTTP_PROXY=tcp://localhost:8125 pm:security
     *   Proxy Guzzle requests through an http proxy.
     * @bootstrap none
     * @table-style default
     * @field-labels
     *   name: Name
     *   version: Installed Version
     * @default-fields name,version
     *
     * @filter-default-field name
     * @return RowsOfFields
     *
     * @throws \Exception
     */
    public function security(array $options = ['no-dev' => false])
    {
        $security_advisories_composer_json = $this->fetchAdvisoryComposerJson();
        $composer_lock_data = $this->loadSiteComposerLock();
        $updates = $this->calculateSecurityUpdates($composer_lock_data, $security_advisories_composer_json, $options['no-dev']);
        if ($updates) {
            $this->suggestComposerCommand($updates);
            return CommandResult::dataWithExitCode(new RowsOfFields($updates), self::EXIT_FAILURE_WITH_CLARITY);
        }
        $this->logger()->success("<info>There are no outstanding security updates for Drupal projects.</info>");
        if ($options['format'] === 'table') {
            return null;
        }
        return new RowsOfFields([]);
    }

    /**
     * Emit suggested Composer command for security updates.
     */
    public function suggestComposerCommand($updates): void
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
     * This function fetches the generated composer.json from the
     * drupal-security-advisories repository or fetches it from another source
     * if the environment variable DRUSH_SECURITY_ADVISORIES_URL is set. The
     * environment variable is not a supported API.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function fetchAdvisoryComposerJson()
    {
        $client = new Client(['handler' => $this->getStack()]);
        $security_advisories_composer_url = getenv('DRUSH_SECURITY_ADVISORIES_URL') ?: 'https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/9.x/composer.json';
        $response = $client->get($security_advisories_composer_url);
        $security_advisories_composer_json = json_decode($response->getBody(), true);
        return $security_advisories_composer_json;
    }

    /**
     * Loads the contents of the local Drupal application's composer.lock file.
     *
     *
     * @throws \Exception
     */
    protected function loadSiteComposerLock(): array
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
     */
    protected function calculateSecurityUpdates(array $composer_lock_data, array $security_advisories_composer_json, bool $excludeDev = false): array
    {
        $updates = [];
        $packages = $composer_lock_data['packages'];
        if (!$excludeDev) {
            $packages = array_merge($composer_lock_data['packages-dev'], $packages);
        }
        $conflict = $security_advisories_composer_json['conflict'];
        foreach ($packages as $package) {
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
     * Packages are discovered via composer.lock file. An exit code of 3
     * indicates that the check completed, and insecure packages were found.
     *
     * @param array $options
     *
     * @return UnstructuredData
     * @throws \Exception
     * @command pm:security-php
     * @validate-php-extension json
     * @aliases sec-php,pm-security-php
     * @option no-dev Only check production dependencies.
     * @bootstrap none
     *
     * @usage drush pm:security-php --format=json
     *   Get security data in JSON format.
     * @usage HTTP_PROXY=tcp://localhost:8125 pm:security
     *   Proxy Guzzle requests through an http proxy.
     */
    public function securityPhp(array $options = ['format' => 'yaml', 'no-dev' => false])
    {
        $result = (new SecurityChecker())->check(self::composerLockPath(), $options['no-dev']);
        if ($result) {
            $suggested_command = "composer why " . implode(' && composer why ', array_keys($result));
            $this->logger()->warning('One or more of your dependencies has an outstanding security update.');
            $this->logger()->notice("Run <comment>$suggested_command</comment> to learn what module requires the package.");
            return CommandResult::dataWithExitCode(new UnstructuredData($result), self::EXIT_FAILURE_WITH_CLARITY);
        }
        $this->logger()->success("There are no outstanding security updates for your dependencies.");
        if ($options['format'] === 'table') {
            return null;
        }
        return new RowsOfFields([]);
    }
}
