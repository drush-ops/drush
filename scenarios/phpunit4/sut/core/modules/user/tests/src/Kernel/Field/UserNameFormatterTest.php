<?php

namespace Drupal\Tests\user\Kernel\Field;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the user_name formatter.
 *
 * @group field
 */
class UserNameFormatterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['field', 'user', 'system'];

  /**
   * @var string
   */
  protected $entityType;

  /**
   * @var string
   */
  protected $bundle;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['field']);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    $this->entityType = 'user';
    $this->bundle = $this->entityType;
    $this->fieldName = 'name';
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to render the fields in.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity, EntityViewDisplayInterface $display) {
    $content = $display->build($entity);
    $content = $this->render($content);
    return $content;
  }

  /**
   * Tests the formatter output.
   */
  public function testFormatter() {
    $user = User::create([
      'name' => 'test name',
    ]);
    $user->save();

    $result = $user->{$this->fieldName}->view(['type' => 'user_name']);
    $this->assertEqual('username', $result[0]['#theme']);
    $this->assertEqual(spl_object_hash($user), spl_object_hash($result[0]['#account']));

    $result = $user->{$this->fieldName}->view(['type' => 'user_name', 'settings' => ['link_to_entity' => FALSE]]);
    $this->assertEqual($user->getDisplayName(), $result[0]['#markup']);

    $user = User::getAnonymousUser();

    $result = $user->{$this->fieldName}->view(['type' => 'user_name']);
    $this->assertEqual('username', $result[0]['#theme']);
    $this->assertEqual(spl_object_hash($user), spl_object_hash($result[0]['#account']));

    $result = $user->{$this->fieldName}->view(['type' => 'user_name', 'settings' => ['link_to_entity' => FALSE]]);
    $this->assertEqual($user->getDisplayName(), $result[0]['#markup']);
    $this->assertEqual($this->config('user.settings')->get('anonymous'), $result[0]['#markup']);
  }

}
