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
     * are available. An exit code of 3 indicates that the check completed, and insecure packages were found.
     *
     * @command pm:security
     * @aliases sec,pm-security
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
            return CommandResult::dataWithExitCode(new RowsOfFields($updates), self::EXIT_FAILURE_WITH_CLARITY);
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
        // We use the v2 branch for now, as per https://github.com/drupal-composer/drupal-security-advisories/pull/11.
        $client = new \GuzzleHttp\Client(['handler' => $this->getStack()]);
        $response = $client->get('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/8.x-v2/composer.json');
        $security_advisories_composer_json = json_decode($response->getBody(), true);
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
     * Packages are discovered via composer.lock file. An exit code of 3
     * indicates that the check completed, and insecure packages were found.
     *
     * Thanks to https://github.com/FriendsOfPHP/security-advisories
     * and Symfony for providing this service.
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
     * @usage HTTP_PROXY=tcp://localhost:8125 pm:security
     *   Proxy Guzzle requests through an http proxy.
     */
    public function securityPhp($options = ['format' => 'yaml'])
    {
        $path = self::composerLockPath();
        // @todo If we ever need user config of Guzzle, see Behat as a model https://coderwall.com/p/nmtuvw/alter-the-curl-timeout-when-using-behat-mink-extension-and-goutte
        $client = new \GuzzleHttp\Client(['handler' => $this->getStack()]);
        $options = [
            'headers'  => ['Accept' => 'application/json'],
            'multipart' => [[
                'name' => 'lock',
                'contents' => fopen($path, 'r'),
            ]],
        ];
        $response = $client->post('https://security.symfony.com/check_lock', $options);
        if ($packages = json_decode($response->getBody(), true)) {
            $suggested_command = "composer why " . implode(' && composer why ', array_keys($packages));
            $this->logger()->warning('One or more of your dependencies has an outstanding security update.');
            $this->logger()->notice("Run <comment>$suggested_command</comment> to learn what module requires the package.");
            return CommandResult::dataWithExitCode(new UnstructuredData($packages), self::EXIT_FAILURE_WITH_CLARITY);
        }
        $this->logger()->success("There are no outstanding security updates for your dependencies.");
    }
}
