<?php

declare(strict_types=1);

use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @var \Drush\Commands\core\PhpScriptCommand $this */
assert(isset($input) && $input instanceof InputInterface);
assert(isset($output) && $output instanceof OutputInterface);
$input_definition = new InputDefinition();
$input_definition->addArgument(new InputArgument('modules', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The modules to update schema versions for.', ['drush_empty_module']));
$input_definition->addOptions([
  new InputOption('--schema-version', null, InputOption::VALUE_REQUIRED, 'The schema version to use.', 8000),
  new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message'),
]);

$arguments = new ArgvInput($input->getArgument('extra'), $input_definition);

if ($arguments->getOption('help')) {
    $output->writeln('<comment>Usage:</comment>');
    $output->writeln(sprintf(
        "  drush php-script %s -- [options] [...modules]\n",
        $input->getArgument('extra')[0]
    ));
    $helper = new DescriptorHelper();
    $helper->describe($output, $input_definition);
    $output->writeln('');
    return;
}

/** @var \Drupal\Core\Update\UpdateHookRegistry $update_hook_registry */
$update_hook_registry = \Drupal::service('update.update_hook_registry');
$module_handler = \Drupal::moduleHandler();

// The schema version to set it to.
$schema_version = (int) $arguments->getOption('schema-version');
$modules = $arguments->getArgument('modules');
foreach ($modules as $module) {
    // Set the installed version for the specific modules.
    $update_hook_registry->setInstalledVersion($module, $schema_version);
}
