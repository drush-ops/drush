<?php

/**
 * @file
 * annotationcommand_adapter.inc
 */

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Consolidation\AnnotatedCommand\CommandProcessor;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\ArrayInput;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Command\DrushInputAdapter;
use Drush\Command\DrushOutputAdapter;
use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Callback function called by HookManager::EXTRACT_OUTPUT to set
 * the backend result.
 */
function annotatedcomand_adapter_backend_result($structured_data) {
  $return = drush_backend_get_result();
  if (empty($return)) {
    drush_backend_set_result($structured_data);
  }
}

