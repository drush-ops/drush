<?php

namespace Drupal\config_test\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\config_test\ConfigTestInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the ConfigTest configuration entity.
 *
 * @ConfigEntityType(
 *   id = "config_test",
 *   label = @Translation("Test configuration"),
 *   handlers = {
 *     "storage" = "Drupal\config_test\ConfigTestStorage",
 *     "list_builder" = "Drupal\config_test\ConfigTestListBuilder",
 *     "form" = {
 *       "default" = "Drupal\config_test\ConfigTestForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "access" = "Drupal\config_test\ConfigTestAccessControlHandler"
 *   },
 *   config_prefix = "dynamic",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/config_test/manage/{config_test}",
 *     "delete-form" = "/admin/structure/config_test/manage/{config_test}/delete",
 *     "enable" = "/admin/structure/config_test/manage/{config_test}/enable",
 *     "disable" = "/admin/structure/config_test/manage/{config_test}/disable",
 *     "collection" = "/admin/structure/config_test",
 *   }
 * )
 */
class ConfigTest extends ConfigEntityBase implements ConfigTestInterface {

  /**
   * The machine name for the configuration entity.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the configuration entity.
   *
   * @var string
   */
  public $label;

  /**
   * The weight of the configuration entity.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * The image style to use.
   *
   * @var string
   */
  public $style;

  /**
   * A protected property of the configuration entity.
   *
   * @var string
   */
  protected $protected_property;

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    \Drupal::state()->set('config_entity_sort', TRUE);
    return parent::sort($a, $b);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Used to test secondary writes during config sync.
    if ($this->id() == 'primary') {
      $secondary = $storage->create([
        'id' => 'secondary',
        'label' => 'Secondary Default',
      ]);
      $secondary->save();
    }
    if ($this->id() == 'deleter') {
      $deletee = $storage->load('deletee');
      $deletee->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      if ($entity->id() == 'deleter') {
        $deletee = $storage->load('deletee');
        $deletee->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    if ($module = \Drupal::state()->get('config_test_new_dependency', FALSE)) {
      $this->addDependency('module', $module);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // Record which entities have this method called on and what dependencies
    // are passed.
    $called = \Drupal::state()->get('config_test.on_dependency_removal_called', []);
    $called[$this->id()] = $dependencies;
    $called[$this->id()]['config'] = array_keys($called[$this->id()]['config']);
    $called[$this->id()]['content'] = array_keys($called[$this->id()]['content']);
    \Drupal::state()->set('config_test.on_dependency_removal_called', $called);

    $changed = parent::onDependencyRemoval($dependencies);
    if (!isset($this->dependencies['enforced']['config'])) {
      return $changed;
    }
    $fix_deps = \Drupal::state()->get('config_test.fix_dependencies', []);
    foreach ($dependencies['config'] as $entity) {
      if (in_array($entity->getConfigDependencyName(), $fix_deps)) {
        $key = array_search($entity->getConfigDependencyName(), $this->dependencies['enforced']['config']);
        if ($key !== FALSE) {
          $changed = TRUE;
          unset($this->dependencies['enforced']['config'][$key]);
        }
      }
    }
    // If any of the dependencies removed still exists, return FALSE.
    if (array_intersect_key(array_flip($this->dependencies['enforced']['config']), $dependencies['config'])) {
      return FALSE;
    }
    return $changed;
  }

  /**
   * Sets the enforced dependencies.
   *
   * @param array $dependencies
   *   A config dependency array.
   *
   * @return $this
   *
   * @see \Drupal\Core\Config\Entity\ConfigDependencyManager
   */
  public function setEnforcedDependencies(array $dependencies) {
    $this->dependencies['enforced'] = $dependencies;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInstallable() {
    return $this->id != 'isinstallable' || \Drupal::state()->get('config_test.isinstallable');
  }

}
