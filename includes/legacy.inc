<?php

use Drupal\Core\Extension\Requirement\RequirementSeverity as RequirementSeverityAlias;

/**
 * Temporary wrapper functions to ease usage across major Drupal versions.
 */

function drush_drupal_requirements_severity(&$requirements) {
    if (class_exists(RequirementSeverityAlias::class)) {
        return RequirementSeverityAlias::maxSeverityFromRequirements($requirements)->value;
    }
    else {
        $severity = 0; // was REQUIREMENT_OK
        foreach ($requirements as $requirement) {
            if (isset($requirement['severity'])) {
                $severity = max($severity, $requirement['severity']);
            }
        }
        return $severity;
    }
}
