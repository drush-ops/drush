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

/**
 * Normalizes a single requirement severity to an integer.
 *
 * Drupal 11.2+ uses RequirementSeverity enums; older versions use integer
 * constants (REQUIREMENT_OK=0, REQUIREMENT_WARNING=1, REQUIREMENT_ERROR=2).
 */
function drush_drupal_requirement_severity_int(mixed $severity): int {
    if ($severity instanceof RequirementSeverityAlias) {
        return $severity->value;
    }
    return (int) $severity;
}

function drush_locale_translation_default_update_options() {
    if (function_exists('_locale_translation_default_update_options')) {
        return _locale_translation_default_update_options();
    }
    else {
        return \Drupal\locale\LocaleDefaultOptions::updateOptions();
    }
}
