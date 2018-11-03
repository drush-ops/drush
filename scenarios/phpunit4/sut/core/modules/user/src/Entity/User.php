<?php

namespace Drupal\user\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\RoleInterface;
use Drupal\user\StatusItem;
use Drupal\user\TimeZoneItem;
use Drupal\user\UserInterface;

/**
 * Defines the user entity class.
 *
 * The base table name here is plural, despite Drupal table naming standards,
 * because "user" is a reserved word in many databases.
 *
 * @ContentEntityType(
 *   id = "user",
 *   label = @Translation("User"),
 *   label_collection = @Translation("Users"),
 *   label_singular = @Translation("user"),
 *   label_plural = @Translation("users"),
 *   label_count = @PluralTranslation(
 *     singular = "@count user",
 *     plural = "@count users",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\user\UserStorage",
 *     "storage_schema" = "Drupal\user\UserStorageSchema",
 *     "access" = "Drupal\user\UserAccessControlHandler",
 *     "list_builder" = "Drupal\user\UserListBuilder",
 *     "views_data" = "Drupal\user\UserViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\user\Entity\UserRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\user\ProfileForm",
 *       "cancel" = "Drupal\user\Form\UserCancelForm",
 *       "register" = "Drupal\user\RegisterForm"
 *     },
 *     "translation" = "Drupal\user\ProfileTranslationHandler"
 *   },
 *   admin_permission = "administer users",
 *   base_table = "users",
 *   data_table = "users_field_data",
 *   label_callback = "user_format_name",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "uid",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/user/{user}",
 *     "edit-form" = "/user/{user}/edit",
 *     "cancel-form" = "/user/{user}/cancel",
 *     "collection" = "/admin/people",
 *   },
 *   field_ui_base_route = "entity.user.admin_form",
 *   common_reference_target = TRUE
 * )
 */
class User extends ContentEntityBase implements UserInterface {

  use EntityChangedTrait;

  /**
   * Stores a reference for a reusable anonymous user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected static $anonymousUser;

  /**
   * {@inheritdoc}
   */
  public function isNew() {
    return !empty($this->enforceIsNew) || $this->id() === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Make sure that the authenticated/anonymous roles are not persisted.
    foreach ($this->get('roles') as $index => $item) {
      if (in_array($item->target_id, [RoleInterface::ANONYMOUS_ID, RoleInterface::AUTHENTICATED_ID])) {
        $this->get('roles')->offsetUnset($index);
      }
    }

    // Store account cancellation information.
    foreach (['user_cancel_method', 'user_cancel_notify'] as $key) {
      if (isset($this->{$key})) {
        \Drupal::service('user.data')->set('user', $this->id(), substr($key, 5), $this->{$key});
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update) {
      $session_manager = \Drupal::service('session_manager');
      // If the password has been changed, delete all open sessions for the
      // user and recreate the current one.
      if ($this->pass->value != $this->original->pass->value) {
        $session_manager->delete($this->id());
        if ($this->id() == \Drupal::currentUser()->id()) {
          \Drupal::service('session')->migrate();
        }
      }

      // If the user was blocked, delete the user's sessions to force a logout.
      if ($this->original->status->value != $this->status->value && $this->status->value == 0) {
        $session_manager->delete($this->id());
      }

      // Send emails after we have the new user object.
      if ($this->status->value != $this->original->status->value) {
        // The user's status is changing; conditionally send notification email.
        $op = $this->status->value == 1 ? 'status_activated' : 'status_blocked';
        _user_mail_notify($op, $this);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    $uids = array_keys($entities);
    \Drupal::service('user.data')->delete(NULL, $uids);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($exclude_locked_roles = FALSE) {
    $roles = [];

    // Users with an ID always have the authenticated user role.
    if (!$exclude_locked_roles) {
      if ($this->isAuthenticated()) {
        $roles[] = RoleInterface::AUTHENTICATED_ID;
      }
      else {
        $roles[] = RoleInterface::ANONYMOUS_ID;
      }
    }

    foreach ($this->get('roles') as $role) {
      if ($role->target_id) {
        $roles[] = $role->target_id;
      }
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRole($rid) {
    return in_array($rid, $this->getRoles());
  }

  /**
   * {@inheritdoc}
   */
  public function addRole($rid) {

    if (in_array($rid, [RoleInterface::AUTHENTICATED_ID, RoleInterface::ANONYMOUS_ID])) {
      throw new \InvalidArgumentException('Anonymous or authenticated role ID must not be assigned manually.');
    }

    $roles = $this->getRoles(TRUE);
    $roles[] = $rid;
    $this->set('roles', array_unique($roles));
  }

  /**
   * {@inheritdoc}
   */
  public function removeRole($rid) {
    $this->set('roles', array_diff($this->getRoles(TRUE), [$rid]));
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    // User #1 has all privileges.
    if ((int) $this->id() === 1) {
      return TRUE;
    }

    return $this->getRoleStorage()->isPermissionInRoles($permission, $this->getRoles());
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword() {
    return $this->get('pass')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPassword($password) {
    $this->get('pass')->value = $password;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail($mail) {
    $this->get('mail')->value = $mail;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    return $this->get('access')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastAccessTime($timestamp) {
    $this->get('access')->value = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastLoginTime() {
    return $this->get('login')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastLoginTime($timestamp) {
    $this->get('login')->value = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return $this->get('status')->value == 1;
  }

  /**
   * {@inheritdoc}
   */
  public function isBlocked() {
    return $this->get('status')->value == 0;
  }

  /**
   * {@inheritdoc}
   */
  public function activate() {
    $this->get('status')->value = 1;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function block() {
    $this->get('status')->value = 0;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    return $this->get('timezone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLangcode($fallback_to_default = TRUE) {
    $language_list = $this->languageManager()->getLanguages();
    $preferred_langcode = $this->get('preferred_langcode')->value;
    if (!empty($preferred_langcode) && isset($language_list[$preferred_langcode])) {
      return $language_list[$preferred_langcode]->getId();
    }
    else {
      return $fallback_to_default ? $this->languageManager()->getDefaultLanguage()->getId() : '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
    $language_list = $this->languageManager()->getLanguages();
    $preferred_langcode = $this->get('preferred_admin_langcode')->value;
    if (!empty($preferred_langcode) && isset($language_list[$preferred_langcode])) {
      return $language_list[$preferred_langcode]->getId();
    }
    else {
      return $fallback_to_default ? $this->languageManager()->getDefaultLanguage()->getId() : '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialEmail() {
    return $this->get('init')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return $this->id() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return $this->id() == 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername() {
    return $this->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName() {
    return $this->get('name')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    $name = $this->getAccountName() ?: \Drupal::config('user.settings')->get('anonymous');
    \Drupal::moduleHandler()->alter('user_format_name', $name, $this);
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function setUsername($username) {
    $this->set('name', $username);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setExistingPassword($password) {
    $this->get('pass')->existing = $password;
  }

  /**
   * {@inheritdoc}
   */
  public function checkExistingPassword(UserInterface $account_unchanged) {
    return strlen($this->get('pass')->existing) > 0 && \Drupal::service('password')->check(trim($this->get('pass')->existing), $account_unchanged->getPassword());
  }

  /**
   * Returns an anonymous user entity.
   *
   * @return \Drupal\user\UserInterface
   *   An anonymous user entity.
   */
  public static function getAnonymousUser() {
    if (!isset(static::$anonymousUser)) {

      // @todo Use the entity factory once available, see
      //   https://www.drupal.org/node/1867228.
      $entity_manager = \Drupal::entityManager();
      $entity_type = $entity_manager->getDefinition('user');
      $class = $entity_type->getClass();

      static::$anonymousUser = new $class([
        'uid' => [LanguageInterface::LANGCODE_DEFAULT => 0],
        'name' => [LanguageInterface::LANGCODE_DEFAULT => ''],
        // Explicitly set the langcode to ensure that field definitions do not
        // need to be fetched to figure out a default.
        'langcode' => [LanguageInterface::LANGCODE_DEFAULT => LanguageInterface::LANGCODE_NOT_SPECIFIED],
      ], $entity_type->id());
    }
    return clone static::$anonymousUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid']->setLabel(t('User ID'))
      ->setDescription(t('The user ID.'));

    $fields['uuid']->setDescription(t('The user UUID.'));

    $fields['langcode']->setLabel(t('Language code'))
      ->setDescription(t('The user language code.'))
      ->setDisplayOptions('form', ['region' => 'hidden']);

    $fields['preferred_langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Preferred language code'))
      ->setDescription(t("The user's preferred language code for receiving emails and viewing the site."))
      // @todo: Define this via an options provider once
      // https://www.drupal.org/node/2329937 is completed.
      ->addPropertyConstraints('value', [
        'AllowedValues' => ['callback' => __CLASS__ . '::getAllowedConfigurableLanguageCodes'],
      ]);

    $fields['preferred_admin_langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Preferred admin language code'))
      ->setDescription(t("The user's preferred language code for viewing administration pages."))
      // @todo: A default value of NULL is ignored, so we have to specify
      // an empty field item structure instead. Fix this in
      // https://www.drupal.org/node/2318605.
      ->setDefaultValue([0 => ['value' => NULL]])
      // @todo: Define this via an options provider once
      // https://www.drupal.org/node/2329937 is completed.
      ->addPropertyConstraints('value', [
        'AllowedValues' => ['callback' => __CLASS__ . '::getAllowedConfigurableLanguageCodes'],
      ]);

    // The name should not vary per language. The username is the visual
    // identifier for a user and needs to be consistent in all languages.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of this user.'))
      ->setRequired(TRUE)
      ->setConstraints([
        // No Length constraint here because the UserName constraint also covers
        // that.
        'UserName' => [],
        'UserNameUnique' => [],
      ]);
    $fields['name']->getItemDefinition()->setClass('\Drupal\user\UserNameItem');

    $fields['pass'] = BaseFieldDefinition::create('password')
      ->setLabel(t('Password'))
      ->setDescription(t('The password of this user (hashed).'))
      ->addConstraint('ProtectedUserField');

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email of this user.'))
      ->setDefaultValue('')
      ->addConstraint('UserMailUnique')
      ->addConstraint('UserMailRequired')
      ->addConstraint('ProtectedUserField');

    $fields['timezone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Timezone'))
      ->setDescription(t('The timezone of this user.'))
      ->setSetting('max_length', 32)
      // @todo: Define this via an options provider once
      // https://www.drupal.org/node/2329937 is completed.
      ->addPropertyConstraints('value', [
        'AllowedValues' => ['callback' => __CLASS__ . '::getAllowedTimezones'],
      ]);
    $fields['timezone']->getItemDefinition()->setClass(TimeZoneItem::class);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('User status'))
      ->setDescription(t('Whether the user is active or blocked.'))
      ->setDefaultValue(FALSE);
    $fields['status']->getItemDefinition()->setClass(StatusItem::class);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the user was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the user was last edited.'))
      ->setTranslatable(TRUE);

    $fields['access'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last access'))
      ->setDescription(t('The time that the user last accessed the site.'))
      ->setDefaultValue(0);

    $fields['login'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last login'))
      ->setDescription(t('The time that the user last logged in.'))
      ->setDefaultValue(0);

    $fields['init'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Initial email'))
      ->setDescription(t('The email address used for initial account creation.'))
      ->setDefaultValue('');

    $fields['roles'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Roles'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDescription(t('The roles the user has.'))
      ->setSetting('target_type', 'user_role');

    return $fields;
  }

  /**
   * Returns the role storage object.
   *
   * @return \Drupal\user\RoleStorageInterface
   *   The role storage object.
   */
  protected function getRoleStorage() {
    return \Drupal::entityManager()->getStorage('user_role');
  }

  /**
   * Defines allowed timezones for the field's AllowedValues constraint.
   *
   * @return string[]
   *   The allowed values.
   */
  public static function getAllowedTimezones() {
    return array_keys(system_time_zones());
  }

  /**
   * Defines allowed configurable language codes for AllowedValues constraints.
   *
   * @return string[]
   *   The allowed values.
   */
  public static function getAllowedConfigurableLanguageCodes() {
    return array_keys(\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE));
  }

}
