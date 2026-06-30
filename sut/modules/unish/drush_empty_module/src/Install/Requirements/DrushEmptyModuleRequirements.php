<?php

declare(strict_types=1);

namespace Drupal\drush_empty_module\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Install time requirements for the drush_empty_module module.
 */
class DrushEmptyModuleRequirements implements InstallRequirementsInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getRequirements(): array
    {
        $requirements = [];
        if (getenv('UNISH_FAIL_INSTALL_REQUIREMENTS') === 'drush_empty_module') {
            // Fail only if the environment variable is set to a specific value.
            $requirements['drush_empty_module'] = [
                'title' => t('Drush empty module: installation failure'),
                'description' => t('Primary install requirements not met.'),
                'severity' => RequirementSeverity::Error,
            ];
            $requirements['drush_empty_module_secondary'] = [
                'title' => t('Drush empty module: installation failure'),
                'description' => t('Secondary install requirements not met.'),
                'severity' => RequirementSeverity::Error,
            ];
        }

        return $requirements;
    }
}
