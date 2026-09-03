<?php

use Drupal\Core\Extension\Requirement\RequirementSeverity as RequirementSeverityAlias;

/**
 * Temporary wrapper functions to ease usage across major Drupal versions.
 */

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

