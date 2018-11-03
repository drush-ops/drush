<?php

namespace Drupal\user\Plugin\migrate\destination;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EmailItem;
use Drupal\Core\Password\PasswordInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a destination plugin for migrating user entities.
 *
 * Example:
 *
 * The example below migrates users and preserves original passwords from a
 * source that has passwords as MD5 hashes without salt. The passwords will be
 * salted and re-hashed before they are saved to the destination Drupal
 * database. The MD5 hash used in the example is a hash of 'password'.
 *
 * The example uses the EmbeddedDataSource source plugin for the sake of
 * simplicity. The mapping between old user_ids and new Drupal uids is saved in
 * the migration map table.
 * @code
 * id: custom_user_migration
 * label: Custom user migration
 * source:
 *   plugin: embedded_data
 *   data_rows:
 *     -
 *       user_id: 1
 *       name: johnsmith
 *       mail: johnsmith@example.com
 *       hash: '5f4dcc3b5aa765d61d8327deb882cf99'
 *   ids:
 *     user_id:
 *       type: integer
 * process:
 *   name: name
 *   mail: mail
 *   pass: hash
 *   status:
 *     plugin: default_value
 *     default_value: 1
 * destination:
 *   plugin: entity:user
 *   md5_passwords: true
 * @endcode
 *
 * For configuration options inherited from the parent class, refer to
 * \Drupal\migrate\Plugin\migrate\destination\EntityContentBase.
 *
 * The example above is about migrating an MD5 password hash. For more examples
 * on different password hash types and a list of other user properties, refer
 * to the handbook documentation:
 * @see https://www.drupal.org/docs/8/api/migrate-api/migrate-destination-plugins-examples/migrating-users
 *
 * @MigrateDestination(
 *   id = "entity:user"
 * )
 */
class EntityUser extends EntityContentBase {

  /**
   * The password service class.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * Builds an user entity destination.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage for this entity type.
   * @param array $bundles
   *   The list of bundles this entity type has.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   The password service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityManagerInterface $entity_manager, FieldTypePluginManagerInterface $field_type_manager, PasswordInterface $password) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_manager, $field_type_manager);
    $this->password = $password;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $entity_type = static::getEntityTypeId($plugin_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity.manager')->getStorage($entity_type),
      array_keys($container->get('entity.manager')->getBundleInfo($entity_type)),
      $container->get('entity.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('password')
    );
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\migrate\MigrateException
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // Do not overwrite the root account password.
    if ($row->getDestinationProperty('uid') == 1) {
      $row->removeDestinationProperty('pass');
    }
    return parent::import($row, $old_destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []) {
    // Do not overwrite the root account password.
    if ($entity->id() != 1) {
      // Set the pre_hashed password so that the PasswordItem field does not hash
      // already hashed passwords. If the md5_passwords configuration option is
      // set we need to rehash the password and prefix with a U.
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem::preSave()
      $entity->pass->pre_hashed = TRUE;
      if (isset($this->configuration['md5_passwords'])) {
        $entity->pass->value = 'U' . $this->password->hash($entity->pass->value);
      }
    }
    return parent::save($entity, $old_destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  protected function processStubRow(Row $row) {
    parent::processStubRow($row);
    // Email address is not defined as required in the base field definition but
    // is effectively required by the UserMailRequired constraint. This means
    // that Entity::processStubRow() did not populate it - we do it here.
    $field_definitions = $this->entityManager
      ->getFieldDefinitions($this->storage->getEntityTypeId(),
        $this->getKey('bundle'));
    $mail = EmailItem::generateSampleValue($field_definitions['mail']);
    $row->setDestinationProperty('mail', reset($mail));

    // @todo Work-around for https://www.drupal.org/node/2602066.
    $name = $row->getDestinationProperty('name');
    if (is_array($name)) {
      $name = reset($name);
    }
    if (mb_strlen($name) > USERNAME_MAX_LENGTH) {
      $row->setDestinationProperty('name', mb_substr($name, 0, USERNAME_MAX_LENGTH));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getHighestId() {
    $highest_id = parent::getHighestId();

    // Every Drupal site must have a user with UID of 1 and it's normal for
    // migrations to overwrite this user.
    if ($highest_id === 1) {
      return 0;
    }
    return $highest_id;
  }

}
