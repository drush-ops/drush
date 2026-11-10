<?php

namespace Drupal\drush_cis\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Config\StorageComparer;
use Symfony\Component\Yaml\Parser;
use Webmozart\PathUtil\Path;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class DrushCisCommands extends DrushCommands {

  /**
   * Imports given config file(s).
   */
  #[CLI\Command(name: 'drush_cis:config-import-single', aliases: ['cis'])]
  #[CLI\Option(name: 'file', description: 'Repeatable. Specify 1 or more paths to files as needed.')]
  #[CLI\Usage(name: 'drush cis --file=path/to/config.file.yml', description: 'Imports given config file.')]
  public function configImportSingle($options = ['file' => ['default']]) {
    $configStorage = \Drupal::service('config.storage');
    $sourceStorage = new StorageReplaceDataWrapper($configStorage);
    $names = [];

    foreach ($options['file'] as $configFile) {
      if (!file_exists($configFile)) {
        $this->logger()->error(dt('Error : config file does not exist') . " : '$configFile'");
        return 1;
      }
      $name = Path::getFilenameWithoutExtension($configFile);
      $ymlFile = new Parser();
      $value = $ymlFile->parse(file_get_contents($configFile));
      $sourceStorage->replaceData($name, $value);
      $names[] = $name;
    }

    $storageComparer = new StorageComparer(
      $sourceStorage,
      $configStorage,
      \Drupal::service('config.manager')
    );

    $configImporter = new ConfigImporter(
      $storageComparer,
      \Drupal::service('event_dispatcher'),
      \Drupal::service('config.manager'),
      \Drupal::lock(),
      \Drupal::service('config.typed'),
      \Drupal::moduleHandler(),
      \Drupal::service('module_installer'),
      \Drupal::service('theme_handler'),
      \Drupal::service('string_translation'),
      \Drupal::service('extension.list.module')
    );

    if ($configImporter->alreadyImporting()) {
      $this->logger()->warning(dt('Already importing.'));
      return 0;
    }

    try {
      if ($configImporter->validate()) {
        $sync_steps = $configImporter->initialize();
        foreach ($sync_steps as $step) {
          $context = [];
          do {
            $configImporter->doSyncStep($step, $context);
          }
          while ($context['finished'] < 1);
        }
      }
    }
    catch (ConfigImporterException $e) {
      $feedback = "Error: unable to import specified config file(s)."
        . PHP_EOL
        . strip_tags(implode(PHP_EOL, $configImporter->getErrors()))
        . PHP_EOL;
      $this->logger()->error($feedback);
      return 2;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return 3;
    }

    $this->logger()->success(
      dt('Config file(s) successfully imported. Config names imported :')
      . " "
      . join(', ', $names)
    );
  }

}
