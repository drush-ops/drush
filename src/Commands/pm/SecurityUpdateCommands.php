<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drush\Boot\DrupalBootLevels;
use GuzzleHttp\Client;
use Composer\Semver\Semver;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Exception;
use Symfony\Component\Filesystem\Path;

/**
 * Check Drupal Composer packages for security updates.
 */
final class SecurityUpdateCommands extends DrushCommands
{
    const SECURITY = 'pm:security';

    /**
     * Return path to composer.lock
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
     */
    #[CLI\Command(name: self::SECURITY, aliases: ['sec', 'pm-security'])]
    #[CLI\Option(name: 'no-dev', description: 'Only check production dependencies.')]
    #[CLI\Usage(name: 'drush pm:security --format=json', description: 'Get security data in JSON format.')]
    #[CLI\Usage(name: 'HTTP_PROXY=tcp://localhost:8125 pm:security', description: 'Proxy Guzzle requests through an http proxy.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
    #[CLI\FieldLabels(labels: ['name' => 'Name', 'version' => 'Installed Version'])]
    #[CLI\DefaultTableFields(fields: ['name', 'version'])]
    #[CLI\FilterDefaultField(field: 'version')]
    public function security(array $options = ['no-dev' => false]): RowsOfFields|CommandResult|null
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
     */
    protected function fetchAdvisoryComposerJson(): mixed
    {
        $client = new Client(['handler' => $this->getStack()]);
        $security_advisories_composer_url = getenv('DRUSH_SECURITY_ADVISORIES_URL') ?: 'https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/9.x/composer.json';
        $response = $client->get($security_advisories_composer_url);
        return json_decode((string)$response->getBody(), true);
    }

    /**
     * Loads the contents of the local Drupal application's composer.lock file.
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
}
