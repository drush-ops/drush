<?php

namespace Drupal\Core\Field\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'password' entity field type.
 *
 * @FieldType(
 *   id = "password",
 *   label = @Translation("Password"),
 *   description = @Translation("An entity field containing a password value."),
 *   no_ui = TRUE,
 * )
 */
class PasswordItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('The hashed password'))
      ->setSetting('case_sensitive', TRUE);
    $properties['existing'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Existing password'));
    $properties['pre_hashed'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Determines if a password needs hashing'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    $entity = $this->getEntity();

    if ($this->pre_hashed) {
      // Reset the pre_hashed value since it has now been used.
      $this->pre_hashed = FALSE;
    }
    elseif ($entity->isNew() || (strlen(trim($this->value)) > 0 && $this->value != $entity->original->{$this->getFieldDefinition()->getName()}->value)) {
      // Allow alternate password hashing schemes.
      $this->value = \Drupal::service('password')->hash(trim($this->value));
      // Abort if the hashing failed and returned FALSE.
      if (!$this->value) {
        throw new EntityMalformedException('The entity does not have a password.');
      }
    }

    if (!$entity->isNew()) {
      // If the password is empty, that means it was not changed, so use the
      // original password.
      if (empty($this->value)) {
        $this->value = $entity->original->{$this->getFieldDefinition()->getName()}->value;
      }
    }
    // Ensure that the existing password is unset to minimise risks of it
    // getting serialized and stored somewhere.
    $this->existing = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We cannot use the parent implementation from StringItem as it does not
    // consider the additional 'existing' property that PasswordItem contains.
    $value = $this->get('value')->getValue();
    $existing = $this->get('existing')->getValue();
    return $value === NULL && $existing === NULL;
  }

}
